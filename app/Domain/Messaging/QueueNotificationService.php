<?php

declare(strict_types=1);

namespace App\Domain\Messaging;

use App\Core\Config;
use App\Core\DB;
use App\Domain\Queue\AppointmentRepository;
use App\Domain\Queue\EtaEngine;
use App\Domain\Queue\QueueOrderingService;
use DateTimeImmutable;

/** Builds the actual SMS bodies and decides when to fire them (doc 8.6 "پیامک‌های صف"). */
final class QueueNotificationService
{
    private AppointmentRepository $appointments;

    private QueueOrderingService $ordering;

    private EtaEngine $eta;

    private SmsNotifier $notifier;

    public function __construct()
    {
        $this->appointments = new AppointmentRepository();
        $this->ordering = new QueueOrderingService();
        $this->eta = new EtaEngine();
        $this->notifier = new SmsNotifier();
    }

    public function syncStaffQueue(int $salonId, int $staffId): void
    {
        $now = new DateTimeImmutable();
        $active = $this->appointments->activeForStaff($salonId, $staffId);
        $ordered = $this->ordering->order($active, $now);
        $etas = $this->eta->computeForStaffQueue($ordered, $now);

        $imminentLow = (int) Config::get('reshen.sms.nearly_up_window_min', 20);
        $imminentHigh = (int) Config::get('reshen.sms.nearly_up_window_max', 30);
        $delayThreshold = (int) Config::get('reshen.sms.delay_threshold_minutes', 20);

        $salon = DB::selectOne('SELECT name FROM salons WHERE id = ?', [$salonId]);
        $staff = DB::selectOne('SELECT name FROM staff WHERE id = ?', [$staffId]);

        // Rank among those still WAITING only — "you're next" means first in
        // line behind whoever is in the chair, not first in the raw list
        // (which always puts the in-chair person at index 0).
        $waitingRank = 0;

        foreach ($ordered as $appt) {
            if ($appt['status'] === 'in_chair') {
                continue;
            }
            $e = $etas[(int) $appt['id']] ?? null;
            if ($e === null) {
                continue;
            }
            $rank = $waitingRank;
            $waitingRank++;

            $minutesUntil = max(0, (int) round(($e['start_p50']->getTimestamp() - $now->getTimestamp()) / 60));

            // "عقب افتادیم" — ETA slipped more than the threshold since the last one we told them.
            if ($appt['estimated_start_at'] !== null) {
                $previousMinutes = (int) round((strtotime($appt['estimated_start_at']) - $now->getTimestamp()) / 60);
                if ($minutesUntil - $previousMinutes >= $delayThreshold) {
                    $body = "شرمنده {$this->firstName($appt)} جان، امروز شلوغ شد و کمی عقبیم.\nنوبتت حدود {$e['start_p50']->format('H:i')} می‌شه.";
                    $this->notifier->notify($salonId, $appt, 'queue_delayed', $body, false);
                }
            }

            $this->appointments->update($salonId, (int) $appt['id'], [
                'estimated_start_at' => $e['start_p50']->format('Y-m-d H:i:s'),
                'estimated_start_max_at' => $e['start_p80']->format('Y-m-d H:i:s'),
                'position_snapshot' => $rank,
            ]);

            // "صندلی آماده‌ست" — became next in line (right after whoever's in the chair).
            if ($rank === 0 && !$this->notifier->alreadySent((int) $appt['id'], 'queue_chair_ready')) {
                $body = "{$this->firstName($appt)} جان، نوبت بعدی توئه! بیا سمت {$staff['name']} تا معطل نشی.";
                $this->notifier->notify($salonId, $appt, 'queue_chair_ready', $body, true);

                continue;
            }

            // "نوبتت نزدیکه" — ETA fell inside the imminent window.
            if ($minutesUntil >= $imminentLow && $minutesUntil <= $imminentHigh
                && !$this->notifier->alreadySent((int) $appt['id'], 'queue_nearly_up')) {
                $ahead = $rank;
                $link = rtrim((string) Config::get('app.url'), '/') . '/q/' . $appt['public_token'];
                $body = "{$this->firstName($appt)} جان، {$ahead} نفر جلوتری، حدود {$minutesUntil} دقیقهٔ دیگه نوبتته.\n"
                    . "راه بیفتی خوبه.\nجای صف: {$link}";
                $this->notifier->notify($salonId, $appt, 'queue_nearly_up', $body, false);
            }
        }
    }

    private function firstName(array $appointment): string
    {
        $name = DB::selectOne('SELECT name FROM customers WHERE id = ?', [$appointment['customer_id']])['name'] ?? null;
        if ($name === null || trim($name) === '') {
            return 'مشتری';
        }

        return trim(explode(' ', $name)[0]);
    }

    public function sendUpcomingReminders(): int
    {
        $hoursList = (array) Config::get('reshen.sms.reminder_hours_before', [24, 2]);
        $sentCount = 0;

        foreach ($hoursList as $hours) {
            $templateCode = "reminder_{$hours}h";
            $target = (new DateTimeImmutable())->modify("+{$hours} hours");
            $windowStart = $target->modify('-2 minutes')->format('Y-m-d H:i:s');
            $windowEnd = $target->modify('+2 minutes')->format('Y-m-d H:i:s');

            $rows = DB::select(
                "SELECT a.*, s.name AS salon_name FROM appointments a JOIN salons s ON s.id = a.salon_id
                 WHERE a.kind = 'booked' AND a.status = 'confirmed'
                 AND a.scheduled_at BETWEEN ? AND ?",
                [$windowStart, $windowEnd]
            );

            foreach ($rows as $appt) {
                if ($this->notifier->alreadySent((int) $appt['id'], $templateCode)) {
                    continue;
                }
                $when = \App\Support\Jalali::format(new DateTimeImmutable($appt['scheduled_at']), 'H:i');
                $body = "{$this->firstName($appt)} جان، یادآوری: نوبتت ساعت {$when} در {$appt['salon_name']} است.";
                if ($this->notifier->notify((int) $appt['salon_id'], $appt, $templateCode, $body, false)) {
                    $sentCount++;
                }
            }
        }

        return $sentCount;
    }
}
