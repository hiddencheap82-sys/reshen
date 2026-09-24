<?php

declare(strict_types=1);

namespace App\Domain\Queue;

use App\Core\Config;
use App\Core\DB;
use App\Domain\Appointment\AppointmentRepository;
use App\Domain\Customer\CustomerRepository;
use App\Domain\Messaging\QueueNotificationService;
use DateTimeImmutable;
use RuntimeException;

/**
 * چرخهٔ عمر صف را می‌گرداند.
 *
 * یک قاعده اینجا قابل مذاکره نیست: «تمام شد» و «تسویه» یک دکمه‌اند.
 *
 * دلیلش رفتار واقعی آرایشگاه است. اگر دو کار جدا باشند، آرایشگر تسویه
 * می‌کند و ثبت «تمام شد» را فراموش می‌کند — و آن‌وقت سیستم هیچ‌وقت
 * یاد نمی‌گیرد که این خدمت چقدر طول کشید، صف جلو نمی‌رود، و تخمین‌های
 * بعدی همه غلط می‌شوند. با یکی بودنشان، همان لحظه که پول گرفته شد
 * مشتری بعدی خودکار روی صندلی می‌نشیند.
 */
final class QueueService
{
    private AppointmentRepository $appointments;

    private QueueOrderingService $ordering;

    private EtaEngine $eta;

    public function __construct()
    {
        $this->appointments = new AppointmentRepository();
        $this->ordering = new QueueOrderingService();
        $this->eta = new EtaEngine();
    }

    /**
     * افزودن مراجعهٔ بدون نوبت، با یک ضربه.
     *
     * اگر آرایشگری انتخاب نشده باشد، کم‌کارترینِ فعال را می‌دهد. سرعت
     * اینجا مهم‌تر از دقت است: مشتری جلوی پیشخوان ایستاده.
     *
     * @param int[] $serviceIds
     */
    public function addWalkin(int $salonId, ?string $customerName, ?string $customerPhone, ?int $staffId, array $serviceIds): array
    {
        if ($serviceIds === []) {
            throw new RuntimeException('حداقل یک خدمت را انتخاب کنید.');
        }

        $staffId ??= (new StaffAssigner())->pickLeastBusy($salonId);
        if ($staffId === null) {
            throw new RuntimeException('هیچ آرایشگر فعالی برای تخصیص وجود ندارد.');
        }

        $customer = (new CustomerRepository())->findOrCreate($salonId, $customerName, $customerPhone);

        $appointmentId = $this->appointments->create($salonId, [
            'customer_id' => $customer['id'],
            'staff_id' => $staffId,
            'kind' => 'walkin',
            'status' => 'queued',
            'queued_at' => date('Y-m-d H:i:s'),
        ]);

        $estimator = new DurationEstimator();
        foreach ($serviceIds as $serviceId) {
            $effective = (new \App\Domain\Catalog\ServiceRepository())->effective($salonId, $staffId, $serviceId);
            $this->appointments->addItem($salonId, $appointmentId, $serviceId, $effective['price'], $effective['duration_minutes']);
        }

        $this->autoStartIfChairFree($salonId, $staffId);
        (new QueueNotificationService())->syncStaffQueue($salonId, $staffId);

        return $this->appointments->find($salonId, $appointmentId);
    }

    /** A04: manual "شروع" — only needed when the chair didn't auto-advance. */
    public function startService(int $salonId, int $appointmentId): void
    {
        $appt = $this->appointments->find($salonId, $appointmentId);
        if ($appt === null) {
            throw new RuntimeException('نوبت یافت نشد.');
        }

        $current = $this->appointments->inChairFor($salonId, (int) $appt['staff_id']);
        if ($current !== null && (int) $current['id'] !== $appointmentId) {
            throw new RuntimeException('این آرایشگر همین الان مشغول است.');
        }

        $this->appointments->update($salonId, $appointmentId, [
            'status' => 'in_chair',
            'actual_start_at' => date('Y-m-d H:i:s'),
        ]);

        (new QueueNotificationService())->syncStaffQueue($salonId, (int) $appt['staff_id']);
    }

    /**
     * A04 + D02: the giant "تمام شد" button. Records the actual end time,
     * feeds the learning engine, advances the chair, and returns the
     * payout-ready totals — payment itself is recorded by PaymentService
     * right after this in the same controller action.
     */
    public function completeService(int $salonId, int $appointmentId): array
    {
        $appt = $this->appointments->find($salonId, $appointmentId);
        if ($appt === null || $appt['status'] !== 'in_chair') {
            throw new RuntimeException('این نوبت روی صندلی نیست.');
        }

        $items = $this->appointments->itemsFor($salonId, $appointmentId);
        $customer = DB::selectOne('SELECT * FROM customers WHERE id = ?', [$appt['customer_id']]);

        $this->appointments->update($salonId, $appointmentId, [
            'status' => 'completed',
            'actual_end_at' => date('Y-m-d H:i:s'),
        ]);
        $appt['actual_end_at'] = date('Y-m-d H:i:s');

        (new DurationLearner())->recordCompletion($salonId, $appt, $items, $customer);

        $this->autoStartIfChairFree($salonId, (int) $appt['staff_id']);
        (new QueueNotificationService())->syncStaffQueue($salonId, (int) $appt['staff_id']);

        return ['appointment' => $appt, 'items' => $items];
    }

    public function cancel(int $salonId, int $appointmentId, string $reason = ''): void
    {
        $appt = $this->appointments->find($salonId, $appointmentId);
        $this->appointments->update($salonId, $appointmentId, [
            'status' => 'cancelled',
            'cancel_reason' => $reason ?: null,
            // از پنل لغو شده، یعنی کار خود آرایشگاه
            'cancelled_by' => 'salon',
        ]);
        if ($appt !== null && $appt['staff_id'] !== null) {
            (new QueueNotificationService())->syncStaffQueue($salonId, (int) $appt['staff_id']);
        }
    }

    public function markNoShow(int $salonId, int $appointmentId): void
    {
        $appt = $this->appointments->find($salonId, $appointmentId);
        $this->appointments->update($salonId, $appointmentId, ['status' => 'no_show']);
        if ($appt !== null) {
            DB::update(
                'customers',
                ['no_show_count' => DB::selectOne('SELECT no_show_count FROM customers WHERE id = ?', [$appt['customer_id']])['no_show_count'] + 1],
                'id = :id',
                ['id' => $appt['customer_id']]
            );
            if ($appt['staff_id'] !== null) {
                (new QueueNotificationService())->syncStaffQueue($salonId, (int) $appt['staff_id']);
            }
        }
    }

    /**
     * اگر صندلی خالی است، نفر بعدیِ صف همان آرایشگر خودکار می‌نشیند.
     *
     * لایهٔ دوم دفاع در برابر فراموشی: آرایشگر وسط کار یادش می‌رود دکمه
     * بزند، و بدون این، صف روی صفحهٔ مشتری‌ها یخ می‌زند.
     */
    private function autoStartIfChairFree(int $salonId, int $staffId): void
    {
        $inChair = $this->appointments->inChairFor($salonId, $staffId);
        if ($inChair !== null) {
            return;
        }

        /*
         * همان ترتیبی که تخمین و پنل نشان می‌دهند — با رزروهای امروز.
         *
         * پیش‌تر فقط حاضرها (queued) مرتب می‌شدند. رزروی که در بازهٔ
         * اولویتش بود ولی مشتری‌اش هنوز نرسیده بود، دیده نمی‌شد و حضوریِ
         * تازه روی صندلی می‌نشست: ساعت ۱۲:۰۸، هفت دقیقه پیش از رزروِ
         * ۱۲:۱۵، با خدمتی نیم‌ساعته. تخمینِ صف می‌گفت «اول رزرو»، صندلی
         * می‌گفت «اول حضوری» — و مشتریِ سرِ وقت ۲۳ دقیقه منتظر می‌ماند.
         *
         * حالا اولین *حاضر* نشانده می‌شود، مگر رزروی همین حالا نوبتش
         * باشد؛ آن‌وقت صندلی منتظرِ او می‌ماند و آرایشگر اگر خواست، با
         * «شروع» حضوری را جلو می‌اندازد. رزروِ دیرکرده (بیرون از بازه)
         * جلوی کسی را نمی‌گیرد — وگرنه یک غیبتِ ثبت‌نشده، صندلی را تا
         * آخر شب قفل می‌کرد.
         */
        $now = new DateTimeImmutable();
        $ordered = $this->ordering->order($this->appointments->activeForStaff($salonId, $staffId), $now);

        foreach ($ordered as $appt) {
            if ($appt['status'] === 'queued') {
                $this->startService($salonId, (int) $appt['id']);

                return;
            }
            if ($this->ordering->hasPriority($appt, $now)) {
                return;
            }
        }
    }

    /**
     * عکس لحظه‌ای کل سالن — برای پنل و صفحهٔ عمومی صف.
     *
     * صف همهٔ آرایشگرهای فعال، مرتب‌شده، با ساعت تخمینی و متنی که
     * مستقیم قابل نشان دادن است.
     */
    public function salonSnapshot(int $salonId): array
    {
        $staffRows = DB::select('SELECT id, name, color FROM staff WHERE salon_id = ? AND is_active = 1 ORDER BY sort_order, id', [$salonId]);
        $now = new DateTimeImmutable();
        $snapshot = [];

        foreach ($staffRows as $staff) {
            $appts = $this->appointments->activeForStaff($salonId, (int) $staff['id']);
            $ordered = $this->ordering->order($appts, $now);
            $etas = $this->eta->computeForStaffQueue($ordered, $now);

            $itemsByAppointment = $this->appointments->itemsForMany(
                $salonId,
                array_map(static fn ($a) => (int) $a['id'], $ordered)
            );

            $rows = [];
            foreach ($ordered as $appt) {
                $e = $etas[(int) $appt['id']] ?? null;
                $display = $e !== null ? $this->eta->displayText($e['position'], $e['start_p50'], $e['start_p80'], $now) : null;
                $rows[] = array_merge($appt, [
                    'eta' => $e,
                    'display' => $display,
                    'items' => $itemsByAppointment[(int) $appt['id']] ?? [],
                ]);
            }

            $snapshot[] = ['staff' => $staff, 'queue' => $rows];
        }

        return $snapshot;
    }

    /** همان نما، ولی از دید یک مشتری — برای لینک «نوبت من». */
    public function customerView(string $publicToken): ?array
    {
        $appt = $this->appointments->findByToken($publicToken);
        if ($appt === null || $appt['staff_id'] === null) {
            return $appt !== null ? ['appointment' => $appt, 'eta' => null, 'display' => null] : null;
        }

        $now = new DateTimeImmutable();
        $queue = $this->appointments->activeForStaff((int) $appt['salon_id'], (int) $appt['staff_id']);
        $ordered = $this->ordering->order($queue, $now);
        $etas = $this->eta->computeForStaffQueue($ordered, $now);

        $e = $etas[(int) $appt['id']] ?? null;
        $display = $e !== null ? $this->eta->displayText($e['position'], $e['start_p50'], $e['start_p80'], $now) : null;

        return ['appointment' => $appt, 'eta' => $e, 'display' => $display];
    }
}
