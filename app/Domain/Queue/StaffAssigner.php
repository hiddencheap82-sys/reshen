<?php

declare(strict_types=1);

namespace App\Domain\Queue;

use App\Core\DB;

/**
 * Phase-1 "any available" assignment: pick the active staff member with the
 * fewest people currently ahead of them. Smarter reassignment when someone
 * no-shows (A22) is phase 3 — deliberately not built here.
 */
final class StaffAssigner
{
    public function pickLeastBusy(int $salonId): ?int
    {
        $row = DB::selectOne(
            "SELECT st.id
             FROM staff st
             LEFT JOIN appointments a ON a.staff_id = st.id AND a.status IN ('queued','in_chair')
             WHERE st.salon_id = ? AND st.is_active = 1
             GROUP BY st.id
             ORDER BY COUNT(a.id) ASC, st.sort_order ASC, st.id ASC
             LIMIT 1",
            [$salonId]
        );

        return $row !== null ? (int) $row['id'] : null;
    }
}
