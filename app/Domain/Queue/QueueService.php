<?php

declare(strict_types=1);

namespace App\Domain\Queue;

use App\Core\Config;
use App\Core\DB;
use App\Domain\Customer\CustomerRepository;
use App\Domain\Messaging\QueueNotificationService;
use DateTimeImmutable;
use RuntimeException;

/**
 * Orchestrates the queue lifecycle. The one non-negotiable rule from the
 * product doc (4.6 / 9.2): "Complete" and "settle" are a single action —
 * a barber can't get paid without the system learning from it, and the
 * next customer's chair status advances automatically the moment they do.
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
     * A02/A03: one-tap walk-in add. If no staff chosen, assigns the least
     * busy active barber (phase-1 "any available" rule).
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

    /** If the chair is empty, whoever is next in that staff's ordered queue becomes in_chair automatically (doc 4.6 defense #2). */
    private function autoStartIfChairFree(int $salonId, int $staffId): void
    {
        $inChair = $this->appointments->inChairFor($salonId, $staffId);
        if ($inChair !== null) {
            return;
        }

        $queue = $this->appointments->activeForStaff($salonId, $staffId);
        $queue = array_filter($queue, static fn ($a) => $a['status'] === 'queued');
        $ordered = $this->ordering->order(array_values($queue));

        if ($ordered !== []) {
            $this->startService($salonId, (int) $ordered[0]['id']);
        }
    }

    /**
     * Full salon snapshot for the panel/public queue displays: every
     * active staff's queue, ordered, with ETA + display text attached.
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

    /** Queue view for one customer's public "my appointment" link. */
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
