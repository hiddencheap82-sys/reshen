<?php

declare(strict_types=1);

namespace App\Domain\Booking;

use App\Core\DB;
use App\Support\Jalali;
use DateTimeImmutable;

/**
 * Free-slot search for online booking (A05/A06). Walks the staff's working
 * hours for the given day in 15-minute steps and keeps any start time whose
 * [start, start+duration] window doesn't collide with an existing booked
 * appointment or a time-off block.
 */
final class SlotFinder
{
    private const STEP_MINUTES = 15;

    /** @return string[] "H:i" start times */
    public function freeSlotsForStaff(int $salonId, int $staffId, DateTimeImmutable $date, int $durationMinutes): array
    {
        $weekday = Jalali::weekday($date);

        $hours = DB::selectOne(
            'SELECT * FROM working_hours WHERE salon_id = ? AND staff_id = ? AND weekday = ?',
            [$salonId, $staffId, $weekday]
        ) ?? DB::selectOne(
            'SELECT * FROM working_hours WHERE salon_id = ? AND staff_id IS NULL AND weekday = ?',
            [$salonId, $weekday]
        );

        if ($hours === null || (int) $hours['is_closed'] === 1) {
            return [];
        }

        $dateStr = $date->format('Y-m-d');
        if ($this->isHoliday($dateStr)) {
            return [];
        }

        $dayStart = new DateTimeImmutable($dateStr . ' ' . $hours['opens_at']);
        $dayEnd = new DateTimeImmutable($dateStr . ' ' . $hours['closes_at']);
        $now = new DateTimeImmutable();

        $busy = $this->busyIntervals($salonId, $staffId, $dateStr);

        $slots = [];
        $cursor = $dayStart;
        while ($cursor->modify("+{$durationMinutes} minutes") <= $dayEnd) {
            $slotEnd = $cursor->modify("+{$durationMinutes} minutes");
            if ($cursor > $now && !$this->overlaps($cursor, $slotEnd, $busy)) {
                $slots[] = $cursor->format('H:i');
            }
            $cursor = $cursor->modify('+' . self::STEP_MINUTES . ' minutes');
        }

        return $slots;
    }

    /** @return array<int,array{0:DateTimeImmutable,1:DateTimeImmutable}> */
    private function busyIntervals(int $salonId, int $staffId, string $dateStr): array
    {
        $intervals = [];

        $appts = DB::select(
            "SELECT scheduled_at, queued_at FROM appointments
             WHERE salon_id = ? AND staff_id = ? AND DATE(COALESCE(scheduled_at, queued_at)) = ?
             AND status IN ('confirmed','queued','in_chair')",
            [$salonId, $staffId, $dateStr]
        );
        foreach ($appts as $a) {
            $start = new DateTimeImmutable($a['scheduled_at'] ?? $a['queued_at']);
            $intervals[] = [$start, $start->modify('+30 minutes')];
        }

        $offs = DB::select(
            'SELECT starts_at, ends_at FROM time_offs WHERE salon_id = ? AND (staff_id = ? OR staff_id IS NULL)
             AND DATE(starts_at) <= ? AND DATE(ends_at) >= ?',
            [$salonId, $staffId, $dateStr, $dateStr]
        );
        foreach ($offs as $o) {
            $intervals[] = [new DateTimeImmutable($o['starts_at']), new DateTimeImmutable($o['ends_at'])];
        }

        return $intervals;
    }

    private function overlaps(DateTimeImmutable $start, DateTimeImmutable $end, array $busy): bool
    {
        foreach ($busy as [$busyStart, $busyEnd]) {
            if ($start < $busyEnd && $end > $busyStart) {
                return true;
            }
        }

        return false;
    }

    private function isHoliday(string $dateStr): bool
    {
        return DB::selectOne('SELECT id FROM holidays WHERE gregorian_date = ?', [$dateStr]) !== null;
    }
}
