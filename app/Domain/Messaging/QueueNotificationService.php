<?php

declare(strict_types=1);

namespace App\Domain\Messaging;

use App\Core\Config;
use App\Core\DB;
use App\Domain\Appointment\AppointmentRepository;
use App\Domain\Queue\EtaEngine;
use App\Domain\Queue\QueueOrderingService;
use App\Support\Clock;
use App\Support\Jalali;
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

        // رتبه فقط میان کسانی که هنوز *منتظرند* شمرده می‌شود.
        //
        // «نفر بعدی تویی» یعنی اول صفِ پشتِ کسی که روی صندلی است، نه
        // اول فهرست خام — که همیشه نفرِ روی صندلی را در جایگاه صفر
        // می‌گذارد. بدون این تفکیک، به کسی که دو نفر جلویش است پیامک
        // «نوبت شماست» می‌رفت.
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

            $isBooked = ($appt['kind'] ?? '') === 'booked' && !empty($appt['scheduled_at']);

            /*
             * «عقب افتادیم» — تخمین از آخرین چیزی که به مشتری گفته‌ایم
             * بیش از آستانه جلو رفته.
             *
             * برای نوبتِ رزروشده، مبنا «دیرترینِ» تخمینِ قبلی و ساعتِ
             * رزرو است. دیر بودن یعنی دیرتر از چیزی که به او قول
             * داده‌ایم — و ساعت رزرو خودش یک قول است. بدون این، نوبت‌هایی
             * که تخمینشان پیش از این اصلاح به‌غلط «الان» ذخیره شده بود،
             * بار اول یک پیامکِ «عقبیم» دروغ می‌گرفتند، در حالی که درست
             * سر وقتشان بودند.
             */
            $baseline = $appt['estimated_start_at'] !== null ? strtotime((string) $appt['estimated_start_at']) : null;
            if ($isBooked) {
                $booked = strtotime((string) $appt['scheduled_at']);
                $baseline = $baseline === null ? $booked : max($baseline, $booked);
            }

            if ($baseline !== null) {
                $previousMinutes = (int) round(($baseline - $now->getTimestamp()) / 60);
                if ($minutesUntil - $previousMinutes >= $delayThreshold) {
                    $this->notifier->notify($salonId, $appt, 'queue_delayed', [
                        'name' => $this->firstName($appt),
                        'time' => Clock::hm($e['start_p50']->format('H:i')),
                    ]);
                }
            }

            $this->appointments->update($salonId, (int) $appt['id'], [
                'estimated_start_at' => $e['start_p50']->format('Y-m-d H:i:s'),
                'estimated_start_max_at' => $e['start_p80']->format('Y-m-d H:i:s'),
                'position_snapshot' => $rank,
            ]);

            /*
             * «نوبت بعدی شماست، لطفاً بیایید.»
             *
             * برای مراجعهٔ حضوری — که همین حالا در سالن نشسته — اول
             * صف بودن کافی است. ولی مشتریِ رزروی در خانه است، و «بیایید»
             * فقط وقتی راست است که ساعتش نزدیک باشد. اول صف بودنِ کسی
             * که ساعت ۶ رزرو کرده، ساعت ۳ هیچ معنایی ندارد.
             */
            $bookedNotYetDue = $isBooked && $minutesUntil > $imminentHigh;

            if ($rank === 0 && !$bookedNotYetDue
                && !$this->notifier->alreadySent((int) $appt['id'], 'queue_chair_ready')) {
                $this->notifier->notify($salonId, $appt, 'queue_chair_ready', [
                    'name' => $this->firstName($appt),
                    'staff' => $staff['name'],
                ]);

                continue;
            }

            // "نوبتت نزدیکه" — ETA fell inside the imminent window.
            if ($minutesUntil >= $imminentLow && $minutesUntil <= $imminentHigh
                && !$this->notifier->alreadySent((int) $appt['id'], 'queue_nearly_up')) {
                /*
                 * لینک صف از متن پیامک حذف شد: الگوی ثبت‌شده نمی‌تواند
                 * لینک متغیر داشته باشد (اپراتور لینک را در متنِ تأییدشده
                 * می‌خواهد، نه به‌عنوان متغیر). مشتری لینک را از پیامک
                 * تأیید رزرو دارد.
                 */
                $this->notifier->notify($salonId, $appt, 'queue_nearly_up', [
                    'name' => $this->firstName($appt),
                    'ahead' => Jalali::toPersianDigits((string) $rank),
                    'minutes' => Jalali::toPersianDigits((string) $minutesUntil),
                ]);
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

    /**
     * چقدر دیر رفتنِ یادآور هنوز قابل قبول است؟
     *
     * جواب از *متنِ خودِ پیامک* درمی‌آید، نه از یک فرمول:
     *
     *   • `reminder_24h` می‌گوید «نوبت **فردا** ساعت …». اگر خیلی دیر
     *     برود، «فردا» دروغ می‌شود. پس حداکثر دو ساعت.
     *
     *   • `reminder_2h` فقط می‌گوید «نوبت شما ساعت … است» — هیچ
     *     مدتی را وعده نمی‌دهد، پس هر وقت پیش از نوبت برسد راست است.
     *
     * برای همین **کوتاه‌ترین یادآور هیچ مرز پایینی ندارد**: آخرین تور
     * ایمنی است و دیر رسیدنش از نرسیدن بهتر.
     *
     * @return int|null دقیقه، یا null یعنی بدون مرز پایینی
     */
    private static function toleranceMinutes(int $hours, array $all): ?int
    {
        if ($all !== [] && $hours === (int) min($all)) {
            return null;
        }

        return (int) max(15, min(120, $hours * 60 / 4));
    }

    public function sendUpcomingReminders(): int
    {
        $hoursList = (array) Config::get('reshen.sms.reminder_hours_before', [24, 2]);
        $sentCount = 0;

        foreach ($hoursList as $hours) {
            $templateCode = "reminder_{$hours}h";

            /*
             * پنجره‌ای که با تأخیر هم می‌بندد، ولی دروغ نمی‌گوید.
             *
             * نسخهٔ قبلی دنبال نوبت‌هایی می‌گشت که دقیقاً در بازهٔ
             * ±۲ دقیقه‌ایِ هدف باشند — پنجره‌ای ۴ دقیقه‌ای، در حالی
             * که کرون هر ۵ دقیقه اجرا می‌شد. هر بار یک دقیقه شکاف
             * می‌ماند و یادآورِ نوبت‌هایی که در آن یک دقیقه می‌افتادند
             * **برای همیشه گم می‌شد** — نه دیر، اصلاً. یکی از هر پنج.
             *
             * راه‌حلِ ساده‌لوحانه این بود که شرط را «سررسید شده» کنیم.
             * ولی آن‌وقت اگر زمان‌بند چند ساعت نخوابد و بعد بیدار شود،
             * پیامکِ «۲۴ ساعت تا نوبتت» یک ساعت پیش از نوبت می‌رسید.
             *
             * پس پنجره ماند، ولی گشاد شد و فقط به عقب: یادآور تا
             * «tolerance» دیر هم می‌رود، بیشتر از آن نه — چون متنش
             * دیگر راست نیست. یادآور بعدی (۲ ساعته) پوششش می‌دهد.
             *
             * شرط created_at لازم است: کسی که سه ساعت پیش از نوبتش
             * رزرو می‌کند نباید پیامکِ «۲۴ ساعت تا نوبتت» بگیرد.
             */
            $tolerance = self::toleranceMinutes((int) $hours, array_map('intval', $hoursList));

            $sql = "SELECT a.*, s.name AS salon_name FROM appointments a JOIN salons s ON s.id = a.salon_id
                     WHERE a.kind = 'booked' AND a.status = 'confirmed'
                       AND a.scheduled_at > NOW()
                       AND a.scheduled_at <= (NOW() + INTERVAL ? HOUR)
                       AND a.created_at < (a.scheduled_at - INTERVAL ? HOUR)";
            $args = [$hours, $hours];

            if ($tolerance !== null) {
                $sql .= ' AND a.scheduled_at > (NOW() + INTERVAL ? HOUR - INTERVAL ? MINUTE)';
                $args[] = $hours;
                $args[] = $tolerance;
            }

            $sql .= ' ORDER BY a.scheduled_at LIMIT 200';

            $rows = DB::select($sql, $args);

            foreach ($rows as $appt) {
                if ($this->notifier->alreadySent((int) $appt['id'], $templateCode)) {
                    continue;
                }
                $when = Clock::hm((new DateTimeImmutable($appt['scheduled_at']))->format('H:i'));
                if ($this->notifier->notify((int) $appt['salon_id'], $appt, $templateCode, [
                    'name' => $this->firstName($appt),
                    'salon' => $appt['salon_name'],
                    'time' => $when,
                ])) {
                    $sentCount++;
                }
            }
        }

        return $sentCount;
    }
}
