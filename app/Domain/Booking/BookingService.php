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
use App\Domain\Queue\AppointmentRepository;
use DateTimeImmutable;
use RuntimeException;

/** A05/A06/A07/A08/A09 — the public, no-install, no-password booking flow. */
final class BookingService
{
    private SlotFinder $slots;

    public function __construct()
    {
        $this->slots = new SlotFinder();
    }

    /** @return array<string,int[]> "H:i" => staff_ids free at that time, for the "any barber" option */
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
}
