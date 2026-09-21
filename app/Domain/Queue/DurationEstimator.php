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
    /*
     * حافظهٔ درون‌درخواستی.
     *
     * آبشار تخمین تا سه کوئری برای هر جفتِ (آرایشگر، خدمت) می‌زند. در
     * یک صف، همان چند خدمت بارها تکرار می‌شوند — ده نفر با «اصلاح مو»
     * یعنی سی کوئریِ یکسان. نتیجه در طول یک درخواست عوض نمی‌شود.
     *
     * @var array<string,array{p50:float,p80:float,source:string}>
     */
    private static array $memo = [];

    /** @return array{p50:float,p80:float,source:string} minutes */
    public function forStaffService(int $staffId, int $serviceId): array
    {
        $key = $staffId . ':' . $serviceId;
        if (isset(self::$memo[$key])) {
            return self::$memo[$key];
        }

        return self::$memo[$key] = $this->resolve($staffId, $serviceId);
    }

    /** کشِ درون‌درخواستی را خالی می‌کند — برای تست، که چند سناریو پشت سر هم دارد. */
    public static function flushCache(): void
    {
        self::$memo = [];
    }

    /** @return array{p50:float,p80:float,source:string} */
    /**
     * آبشار تخمین، در یک کوئری.
     *
     * سه پرسش پشت سر هم بود (آمار یادگرفته‌شده، عدد اختصاصی آرایشگر،
     * عدد اسمی خدمت) و هر کدام یک رفت‌وبرگشت. صفحهٔ صف که هر ۱۵ ثانیه
     * تازه می‌شود، همین را برای هر جفتِ آرایشگر-خدمت تکرار می‌کرد.
     *
     * ترتیب اولویت همان است؛ فقط به‌جای سه پرسشِ متوالی، یک LEFT JOIN
     * هر سه را می‌آورد و تصمیم در PHP گرفته می‌شود.
     *
     * @return array{p50:float,p80:float,source:string}
     */
    private function resolve(int $staffId, int $serviceId): array
    {
        $minSamples = (int) Config::get('reshen.estimation.min_samples_for_learning', 8);

        $row = DB::selectOne(
            'SELECT sv.duration_minutes          AS nominal,
                    ss.duration_minutes          AS staff_override,
                    ds.sample_count              AS samples,
                    ds.p50_minutes               AS learned_p50,
                    ds.p80_minutes               AS learned_p80
             FROM services sv
             LEFT JOIN staff_service ss
                    ON ss.service_id = sv.id AND ss.staff_id = ?
             LEFT JOIN duration_stats ds
                    ON ds.service_id = sv.id AND ds.staff_id = ?
             WHERE sv.id = ?',
            [$staffId, $staffId, $serviceId]
        );

        // ۱. آنچه از مدت‌های واقعی یاد گرفته‌ایم — وقتی نمونهٔ کافی هست
        if ($row !== null && $row['samples'] !== null && (int) $row['samples'] >= $minSamples
            && $row['learned_p50'] !== null) {
            return [
                'p50' => (float) $row['learned_p50'],
                'p80' => (float) $row['learned_p80'],
                'source' => 'learned',
            ];
        }

        // ۲. عددی که خود آرایشگر برای این خدمت گذاشته
        if ($row !== null && $row['staff_override'] !== null) {
            $m = (float) $row['staff_override'];

            return ['p50' => $m, 'p80' => $m * 1.3, 'source' => 'staff_override'];
        }

        // ۳. عدد اسمی خدمت
        if ($row !== null && $row['nominal'] !== null) {
            $m = (float) $row['nominal'];

            return ['p50' => $m, 'p80' => $m * 1.3, 'source' => 'nominal'];
        }

        // ۴. آخرین پناه
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
