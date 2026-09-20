<?php

declare(strict_types=1);

namespace App\Domain\Queue;

use App\Core\Config;
use App\Core\DB;

/**
 * The estimation waterfall from product doc 8.6:
 *   1. learned p50/p80 for (staff, service) — once >= min_samples real samples exist
 *   2. that barber's own override duration for the service
 *   3. the salon's nominal duration for the service
 *   4. a hard 30-minute fallback
 * Then the customer's personal pace factor (if they have enough history)
 * is applied on top, clamped so a single bad data point can't blow it up.
 */
final class DurationEstimator
{
    /** @return array{p50:float,p80:float,source:string} minutes */
    public function forStaffService(int $staffId, int $serviceId): array
    {
        $minSamples = (int) Config::get('reshen.estimation.min_samples_for_learning', 8);

        $stats = DB::selectOne(
            'SELECT sample_count, p50_minutes, p80_minutes FROM duration_stats WHERE staff_id = ? AND service_id = ?',
            [$staffId, $serviceId]
        );

        if ($stats !== null && (int) $stats['sample_count'] >= $minSamples) {
            return ['p50' => (float) $stats['p50_minutes'], 'p80' => (float) $stats['p80_minutes'], 'source' => 'learned'];
        }

        $override = DB::selectOne(
            'SELECT duration_minutes FROM staff_service WHERE staff_id = ? AND service_id = ? AND duration_minutes IS NOT NULL',
            [$staffId, $serviceId]
        );
        if ($override !== null) {
            $m = (float) $override['duration_minutes'];

            return ['p50' => $m, 'p80' => $m * 1.3, 'source' => 'staff_override'];
        }

        $service = DB::selectOne('SELECT duration_minutes FROM services WHERE id = ?', [$serviceId]);
        if ($service !== null) {
            $m = (float) $service['duration_minutes'];

            return ['p50' => $m, 'p80' => $m * 1.3, 'source' => 'nominal'];
        }

        $fallback = (float) Config::get('reshen.estimation.fallback_minutes', 30);

        return ['p50' => $fallback, 'p80' => $fallback * 1.3, 'source' => 'fallback'];
    }

    /** Applies the customer's personal pace multiplier, if active. */
    public function customerFactor(?array $customer): float
    {
        if ($customer === null || $customer['duration_factor'] === null) {
            return 1.0;
        }

        $min = (float) Config::get('reshen.estimation.customer_factor_min', 0.7);
        $max = (float) Config::get('reshen.estimation.customer_factor_max', 1.5);
        $factor = (float) $customer['duration_factor'];

        return max($min, min($max, $factor));
    }

    /**
     * Expected duration (p50, p80) in minutes for a full appointment
     * (all its services combined), with the customer's pace factor applied.
     *
     * @param int[] $serviceIds
     * @return array{p50:float,p80:float}
     */
    public function forAppointment(int $staffId, array $serviceIds, ?array $customer): array
    {
        $p50 = 0.0;
        $p80 = 0.0;
        foreach ($serviceIds as $serviceId) {
            $est = $this->forStaffService($staffId, $serviceId);
            $p50 += $est['p50'];
            $p80 += $est['p80'];
        }

        $factor = $this->customerFactor($customer);

        return ['p50' => $p50 * $factor, 'p80' => $p80 * $factor];
    }
}
