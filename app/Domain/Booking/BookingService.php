<?php

declare(strict_types=1);

namespace App\Domain\Booking;

use App\Core\DB;
use App\Domain\Catalog\ServiceRepository;
use App\Domain\Customer\CustomerRepository;
use App\Domain\Messaging\SmsManager;
use App\Domain\Messaging\SmsNotifier;
use App\Support\Clock;
use App\Support\JalaliCalendar;
use App\Domain\Appointment\AppointmentRepository;
use DateTimeImmutable;
use RuntimeException;

/**
 * مسیر رزرو عمومی — بدون نصب، بدون رمز.
 *
 * مشتری QR را اسکن می‌کند و تا ثبت نوبت هیچ حسابی نمی‌سازد و هیچ
 * رمزی نمی‌گذارد. هر گامی که اضافه شود، درصدی از مشتری‌ها همان‌جا
 * می‌ایستند — و آرایشگاه دوباره برمی‌گردد سر تلفن.
 */
final class BookingService
{
    private SlotFinder $slots;

    public function __construct()
    {
        $this->slots = new SlotFinder();
    }

    /** @return array<string,int[]> «ساعت:دقیقه» ← آرایشگرهای آزاد در آن لحظه (برای گزینهٔ «هر آرایشگری») */
    public function freeSlots(int $salonId, ?int $staffId, DateTimeImmutable $date, int $durationMinutes): array
    {
        // تقویم برای هر روزِ ماه یک بار اینجا می‌آید؛ فهرست آرایشگرها
        // در طول یک درخواست عوض نمی‌شود.
        $staffIds = $staffId !== null ? [$staffId] : $this->activeStaffIds($salonId);

        $byTime = [];
        foreach ($staffIds as $sid) {
            foreach ($this->slots->freeSlotsForStaff($salonId, (int) $sid, $date, $durationMinutes) as $time) {
                $byTime[$time][] = (int) $sid;
            }
        }
        ksort($byTime);

        return $byTime;
    }

    /**
     * طول یک سانس سالن، مستقل از خدمتی که مشتری انتخاب می‌کند.
     *
     * مسیر رزرو اول روز و سانس را می‌گیرد و بعد خدمت را می‌پرسد، پس
     * موقع ساختن سانس‌ها هنوز مدت خدمت معلوم نیست. مبنا همان طول
     * سانسِ خود سالن است؛ جا شدن خدمت در سانس را پنل آرایشگاه
     * بررسی می‌کند.
     */
    public function sessionMinutes(int $salonId): int
    {
        return $this->slots->stepMinutes($salonId);
    }

    /** @var array<int,int[]> */
    private static array $staffCache = [];

    /** @return int[] */
    private function activeStaffIds(int $salonId): array
    {
        if (!isset(self::$staffCache[$salonId])) {
            self::$staffCache[$salonId] = array_map('intval', array_column(
                DB::select('SELECT id FROM staff WHERE salon_id = ? AND is_active = 1 ORDER BY sort_order, id', [$salonId]),
                'id'
            ));
        }

        return self::$staffCache[$salonId];
    }

    /** کشِ درون‌درخواستی را خالی می‌کند — برای تست. */
    public static function flushCache(): void
    {
        self::$staffCache = [];
        SlotFinder::flushCache();
    }

    /** @param int[] $serviceIds */
    public function createBooking(
        int $salonId,
        ?int $preferredStaffId,
        array $serviceIds,
        DateTimeImmutable $date,
        string $time,
        string $phoneRaw,
        ?string $name,
        ?string $ip = null,
    ): array {
        if ($serviceIds === []) {
            throw new RuntimeException('حداقل یک خدمت را انتخاب کنید.');
        }

        $serviceRepo = new ServiceRepository();
        $nominalDuration = array_sum(array_map(
            static fn (int $id) => $serviceRepo->find($salonId, $id)['duration_minutes'] ?? 30,
            $serviceIds
        ));

        $free = $this->freeSlots($salonId, $preferredStaffId, $date, $nominalDuration);
        if (!isset($free[$time]) || $free[$time] === []) {
            throw new RuntimeException('این بازه دیگر آزاد نیست. لطفاً بازهٔ دیگری انتخاب کنید.');
        }

        $staffId = $preferredStaffId ?? $free[$time][0];
        $scheduledAt = new DateTimeImmutable($date->format('Y-m-d') . ' ' . $time);

        $customer = (new CustomerRepository())->findOrCreate($salonId, $name, $phoneRaw);

        $appointments = new AppointmentRepository();
        $appointmentId = $appointments->create($salonId, [
            'customer_id' => $customer['id'],
            'staff_id' => $staffId,
            'kind' => 'booked',
            'status' => 'confirmed',
            'scheduled_at' => $scheduledAt->format('Y-m-d H:i:s'),
            // برای محدودیت نرخ (ت-۳۵) — نه برای چیز دیگری
            'created_ip' => $ip,
        ]);

        foreach ($serviceIds as $serviceId) {
            $effective = $serviceRepo->effective($salonId, $staffId, $serviceId);
            $appointments->addItem($salonId, $appointmentId, $serviceId, $effective['price'], $effective['duration_minutes']);
        }

        $appointment = $appointments->find($salonId, $appointmentId);
        $this->sendConfirmation($salonId, $appointment, $customer);
        $this->tellTheSalon($salonId, $appointment, $customer);

        return $appointment;
    }

    public function cancelByToken(string $token, string $reason = ''): bool
    {
        $appointments = new AppointmentRepository();
        $appt = $appointments->findByToken($token);
        if ($appt === null || !in_array($appt['status'], ['confirmed', 'queued'], true)) {
            return false;
        }
        $appointments->update((int) $appt['salon_id'], (int) $appt['id'], [
            'status' => 'cancelled',
            'cancel_reason' => $reason ?: 'لغو توسط مشتری',
            // این مسیر فقط از دست مشتری می‌آید (کارت نوبت یا «نوبت‌های من»)
            'cancelled_by' => 'customer',
        ]);

        return true;
    }

    /**
     * پیامک تأیید رزرو.
     *
     * از راه الگو می‌رود نه متن آزاد (ت-۲۹): روی خط خدماتی، متنِ آزاد
     * تحویل نمی‌شود ولی در پنل «ارسال شد» می‌خورد.
     *
     * لینک پیگیری از متن حذف شد چون الگوی ثبت‌شده نمی‌تواند لینکِ متغیر
     * داشته باشد. مشتری از همان صفحه‌ای که رزرو کرده لینکش را می‌بیند.
     */
    private function sendConfirmation(int $salonId, array $appointment, array $customer): void
    {
        if ($customer['phone'] === null) {
            return;
        }

        $salon = DB::selectOne('SELECT name FROM salons WHERE id = ?', [$salonId]);
        $at = new DateTimeImmutable($appointment['scheduled_at']);

        (new SmsNotifier())->notify($salonId, $appointment, 'booking_confirmed', [
            'name' => trim(explode(' ', (string) ($customer['name'] ?? 'مشتری'))[0]) ?: 'مشتری',
            'salon' => $salon['name'] ?? '',
            'date' => JalaliCalendar::humanDate($at),
            'time' => Clock::hm($at->format('H:i')),
        ]);
    }

    /**
     * خبر دادن به خودِ سالن.
     *
     * چرا: صفحهٔ «صف زنده» فقط امروز را نشان می‌دهد. نوبتی که مشتری
     * برای هفتهٔ بعد گرفته، تا وقتی کسی خودش به «رزروها» سر نزند دیده
     * نمی‌شود — و آرایشگری که تازه از دفترچه آمده، به سیستمی که
     * ساکت است اعتماد نمی‌کند.
     *
     * فقط برای رزرو اینترنتی معنی دارد. نوبتی که خودِ سالن پشت
     * پیشخوان ثبت کرده، خبر دادن ندارد — آرایشگر همان لحظه آنجا
     * بوده. اینجا همه‌شان اینترنتی‌اند چون `createBooking` فقط از
     * مسیر عمومی صدا زده می‌شود؛ رزرو دستی مسیر خودش را دارد.
     *
     * ستون `salon_notified_at` جلوی پیامک تکراری را می‌گیرد: بدون
     * آن، هر بار که این متد به هر دلیلی دوباره اجرا شود، هم پول
     * می‌رود هم آرایشگر دو بار خبردار می‌شود.
     */
    private function tellTheSalon(int $salonId, array $appointment, array $customer): void
    {
        $salon = DB::selectOne('SELECT phone FROM salons WHERE id = ?', [$salonId]);
        $salonPhone = trim((string) ($salon['phone'] ?? ''));

        // شمارهٔ سالن اختیاری است. نبودنش یعنی این خبر نمی‌رود — که
        // صفحهٔ تنظیمات هم همین را می‌گوید.
        if ($salonPhone === '') {
            return;
        }

        if ($appointment['salon_notified_at'] !== null) {
            return;
        }

        $at = new DateTimeImmutable($appointment['scheduled_at']);

        $sent = (new SmsNotifier())->notify(
            $salonId,
            $appointment,
            'salon_new_booking',
            [
                'name' => (string) ($customer['name'] ?? '') !== ''
                    ? (string) $customer['name']
                    : (string) $customer['phone'],
                'date' => JalaliCalendar::humanDate($at),
                'time' => Clock::hm($at->format('H:i')),
            ],
            null,
            $salonPhone
        );

        if ($sent) {
            DB::update(
                'appointments',
                ['salon_notified_at' => date('Y-m-d H:i:s')],
                'id = :id',
                ['id' => $appointment['id']]
            );
        }
    }
}
