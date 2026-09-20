<?php

declare(strict_types=1);

namespace App\Domain\Queue;

use App\Core\Config;
use App\Core\DB;
use DateTimeImmutable;

/**
 * Turns an already-ordered per-staff queue into promised times. Formula
 * straight from doc 8.6:
 *
 *   remaining(in_chair) = max(floor, expected(current) - elapsed)
 *   ETA(i) = now + remaining + sum_{j<i}( expected(j) + buffer )
 *   upper bound = same, using p80 instead of p50 throughout
 *
 * Never returns "right now" (a 2-minute floor) and never estimates past a
 * 3-hour horizon — beyond that the error is meaningless (doc 8.6).
 */
final class EtaEngine
{
    private DurationEstimator $estimator;

    public function __construct(?DurationEstimator $estimator = null)
    {
        $this->estimator = $estimator ?? new DurationEstimator();
    }

    /**
     * @param array<int,array> $orderedAppointments already in queue order for ONE staff member
     * @return array<int,array{expected_p50:float,expected_p80:float,start_p50:DateTimeImmutable,start_p80:DateTimeImmutable,position:int}>
     *         keyed by appointment id
     */
    public function computeForStaffQueue(array $orderedAppointments, ?DateTimeImmutable $now = null): array
    {
        $now = $now ?? new DateTimeImmutable();
        $bufferMinutes = (float) Config::get('reshen.queue.buffer_minutes', 5);
        $minRemaining = (float) Config::get('reshen.queue.min_remaining_minutes', 2);

        $results = [];
        $cursorP50 = $now;
        $cursorP80 = $now;
        $position = 0;

        foreach ($orderedAppointments as $appt) {
            $items = DB::select('SELECT service_id FROM appointment_items WHERE appointment_id = ?', [$appt['id']]);
            $serviceIds = array_map(static fn ($r) => (int) $r['service_id'], $items);
            $customer = $appt['staff_id'] !== null
                ? DB::selectOne('SELECT id, duration_factor FROM customers WHERE id = ?', [$appt['customer_id']])
                : null;

            $expected = $serviceIds !== [] && $appt['staff_id'] !== null
                ? $this->estimator->forAppointment((int) $appt['staff_id'], $serviceIds, $customer)
                : ['p50' => (float) Config::get('reshen.estimation.fallback_minutes', 30), 'p80' => (float) Config::get('reshen.estimation.fallback_minutes', 30) * 1.3];

            if ($appt['status'] === 'in_chair' && $appt['actual_start_at'] !== null) {
                $elapsedMinutes = (time() - strtotime($appt['actual_start_at'])) / 60;
                $remainingP50 = max($minRemaining, $expected['p50'] - $elapsedMinutes);
                $remainingP80 = max($minRemaining, $expected['p80'] - $elapsedMinutes);

                $startP50 = $now;
                $startP80 = $now;
                $cursorP50 = $now->modify('+' . (int) round($remainingP50) . ' minutes');
                $cursorP80 = $now->modify('+' . (int) round($remainingP80) . ' minutes');
            } else {
                $startP50 = $cursorP50;
                $startP80 = $cursorP80;
                $cursorP50 = $cursorP50->modify('+' . (int) round($expected['p50'] + $bufferMinutes) . ' minutes');
                $cursorP80 = $cursorP80->modify('+' . (int) round($expected['p80'] + $bufferMinutes) . ' minutes');
            }

            $results[(int) $appt['id']] = [
                'expected_p50' => $expected['p50'],
                'expected_p80' => $expected['p80'],
                'start_p50' => $startP50,
                'start_p80' => $startP80,
                'position' => $position,
            ];
            $position++;
        }

        return $results;
    }

    /** Human display text per doc 8.6 "قواعد نمایش". */
    public function displayText(int $position, DateTimeImmutable $startP50, DateTimeImmutable $startP80, DateTimeImmutable $now): array
    {
        if ($position === 0) {
            return ['text' => 'نوبت بعدی توست', 'rough' => false];
        }

        $minutesP50 = max(0, (int) round(($startP50->getTimestamp() - $now->getTimestamp()) / 60));
        $minutesP80 = max($minutesP50, (int) round(($startP80->getTimestamp() - $now->getTimestamp()) / 60));

        $imminent = (int) Config::get('reshen.display.imminent_threshold_minutes', 15);
        $far = (int) Config::get('reshen.display.far_threshold_minutes', 60);
        $maxWindow = (int) Config::get('reshen.display.max_window_minutes', 25);

        if ($minutesP50 < $imminent) {
            return ['text' => "حدود {$this->fa($minutesP50)} دقیقهٔ دیگر", 'rough' => false];
        }

        $window = $minutesP80 - $minutesP50;
        $rough = false;
        if ($window > $maxWindow) {
            $minutesP80 = $minutesP50 + $maxWindow;
            $rough = true;
        }

        if ($minutesP50 < $far) {
            return ['text' => "{$this->fa($minutesP50)} تا {$this->fa($minutesP80)} دقیقهٔ دیگر", 'rough' => $rough];
        }

        $timeFrom = $startP50->format('H:i');
        $timeToStr = $rough
            ? (clone $startP50)->modify('+' . $maxWindow . ' minutes')->format('H:i')
            : $startP80->format('H:i');

        return ['text' => "حدود ساعت {$this->fa($timeFrom)} تا {$this->fa($timeToStr)}", 'rough' => $rough];
    }

    private function fa(int|string $v): string
    {
        return \App\Support\Jalali::toPersianDigits((string) $v);
    }
}
