<?php

declare(strict_types=1);

namespace App\Domain\Queue;

use App\Core\Config;
use DateTimeImmutable;

/**
 * The single-queue ordering rule (doc 8.6):
 *   1. whoever is in the chair right now (at most one)
 *   2. a booked appointment inside its priority window (10 min before to
 *      10 min after its slot) outranks any walk-in
 *   3. everyone else, by arrival order
 *
 * This is what makes "one queue, not two" actually fair and explainable:
 * a customer who booked 6:00 and is 40 minutes late does not cut in front
 * of someone who has been standing there for half an hour.
 */
final class QueueOrderingService
{
    /**
     * @param array<int,array> $appointments raw rows (status in confirmed/queued/in_chair)
     * @return array<int,array> the same rows, reordered
     */
    public function order(array $appointments, ?DateTimeImmutable $now = null): array
    {
        $now = $now ?? new DateTimeImmutable();
        $windowMinutes = (int) Config::get('reshen.queue.priority_window_minutes', 10);

        $inChair = [];
        $priorityBooked = [];
        $rest = [];

        foreach ($appointments as $appt) {
            if ($appt['status'] === 'in_chair') {
                $inChair[] = $appt;

                continue;
            }

            if ($appt['kind'] === 'booked' && $appt['scheduled_at'] !== null && $this->withinPriorityWindow($appt['scheduled_at'], $now, $windowMinutes)) {
                $priorityBooked[] = $appt;

                continue;
            }

            $rest[] = $appt;
        }

        usort($priorityBooked, static fn ($a, $b) => strtotime($a['scheduled_at']) <=> strtotime($b['scheduled_at']));
        usort($rest, static function ($a, $b) {
            $aKey = strtotime($a['queued_at'] ?? $a['scheduled_at'] ?? $a['created_at']);
            $bKey = strtotime($b['queued_at'] ?? $b['scheduled_at'] ?? $b['created_at']);

            return $aKey <=> $bKey;
        });

        return array_merge($inChair, $priorityBooked, $rest);
    }

    private function withinPriorityWindow(string $scheduledAt, DateTimeImmutable $now, int $windowMinutes): bool
    {
        $scheduled = strtotime($scheduledAt);
        $nowTs = $now->getTimestamp();

        return $nowTs >= ($scheduled - $windowMinutes * 60) && $nowTs <= ($scheduled + $windowMinutes * 60);
    }
}
