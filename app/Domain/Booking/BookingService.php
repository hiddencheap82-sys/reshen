<?php

declare(strict_types=1);

namespace App\Domain\Booking;

use App\Core\DB;
use App\Domain\Catalog\ServiceRepository;
use App\Domain\Customer\CustomerRepository;
use App\Domain\Messaging\SmsManager;
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
        $staffIds = $staffId !== null
            ? [$staffId]
            : array_column(DB::select('SELECT id FROM staff WHERE salon_id = ? AND is_active = 1', [$salonId]), 'id');

        $byTime = [];
        foreach ($staffIds as $sid) {
            foreach ($this->slots->freeSlotsForStaff($salonId, (int) $sid, $date, $durationMinutes) as $time) {
                $byTime[$time][] = (int) $sid;
            }
        }
        ksort($byTime);

        return $byTime;
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
        $appointments->update((int) $appt['salon_id'], (int) $appt['id'], ['status' => 'cancelled', 'cancel_reason' => $reason ?: 'لغو توسط مشتری']);

        return true;
    }

    private function sendConfirmation(int $salonId, array $appointment, array $customer): void
    {
        if ($customer['phone'] === null) {
            return;
        }
        $salon = DB::selectOne('SELECT name FROM salons WHERE id = ?', [$salonId]);
        $link = rtrim((string) \App\Core\Config::get('app.url'), '/') . '/q/' . $appointment['public_token'];
        $when = \App\Support\Jalali::format(new DateTimeImmutable($appointment['scheduled_at']), 'D j M، H:i');

        $message = "نوبت شما در {$salon['name']} ثبت شد: {$when}\nپیگیری نوبت: {$link}";
        SmsManager::send($customer['phone'], $message);
    }
}
