<?php

declare(strict_types=1);

namespace App\Domain\Salon;

use App\Core\DB;

final class WorkingHoursRepository
{
    /** @return array<int,array> indexed by weekday 0..6 for the salon-wide default (staff_id null) */
    public function salonDefaults(int $salonId): array
    {
        $rows = DB::select(
            'SELECT * FROM working_hours WHERE salon_id = ? AND staff_id IS NULL ORDER BY weekday',
            [$salonId]
        );
        $byDay = [];
        foreach ($rows as $r) {
            $byDay[(int) $r['weekday']] = $r;
        }

        return $byDay;
    }

    public function setSalonDay(int $salonId, int $weekday, string $opensAt, string $closesAt, bool $closed): void
    {
        $existing = DB::selectOne(
            'SELECT id FROM working_hours WHERE salon_id = ? AND staff_id IS NULL AND weekday = ?',
            [$salonId, $weekday]
        );
        $data = ['opens_at' => $opensAt, 'closes_at' => $closesAt, 'is_closed' => $closed ? 1 : 0];

        if ($existing) {
            DB::update('working_hours', $data, 'id = :id', ['id' => $existing['id']]);

            return;
        }

        DB::insert('working_hours', array_merge($data, ['salon_id' => $salonId, 'staff_id' => null, 'weekday' => $weekday]));
    }
}
