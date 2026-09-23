<?php

declare(strict_types=1);

namespace App\Domain\Salon;

use App\Core\DB;
use App\Support\Jalali;
use DateTimeImmutable;

/**
 * «امروز در یک نگاه» — عددهای سالن برای صاحبش.
 *
 * چرا جدا از صفحهٔ صف: صف ابزار *اجرای* روز است و هر چند ثانیه عوض
 * می‌شود؛ آرایشگر وسط کار بازش می‌کند و باید فقط بگوید نفر بعدی کیست.
 * این صفحه ابزار *دیدنِ* کسب‌وکار است و صاحب سالن صبح و شب بازش
 * می‌کند. قاطی کردنشان یعنی هیچ‌کدام کار خودش را خوب نمی‌کند.
 *
 * چرا این کلاس فقط می‌خواند و چیزی نمی‌نویسد: هر عددی اینجا از رکورد
 * واقعی درمی‌آید، نه از شمارندهٔ جداگانه. شمارنده روزی با واقعیت فرق
 * می‌کند و آن روز هیچ‌کس نمی‌فهمد کدام راست می‌گوید.
 */
final class SalonDashboard
{
    public function __construct(private int $salonId)
    {
    }

    /**
     * کارهایی که امروز باید برایشان کاری کرد.
     *
     * عمداً فقط چیزهایی که *اقدام* دارند. عددِ تزئینی در این بخش
     * نمی‌آید، وگرنه بعد از یک هفته کل بخش نادیده گرفته می‌شود.
     */
    public function needsAttention(string $date): array
    {
        $unpaid = DB::selectOne(
            "SELECT COUNT(*) AS c
               FROM appointments a
               LEFT JOIN payments p ON p.appointment_id = a.id
              WHERE a.salon_id = ? AND a.status = 'completed'
                AND DATE(a.actual_end_at) = ? AND p.id IS NULL",
            [$this->salonId, $date]
        );

        $noShows = DB::selectOne(
            "SELECT COUNT(*) AS c FROM appointments
              WHERE salon_id = ? AND status = 'no_show'
                AND DATE(COALESCE(scheduled_at, queued_at, created_at)) = ?",
            [$this->salonId, $date]
        );

        /*
         * نوبت‌هایی که ساعتشان گذشته و هنوز در صف مانده‌اند.
         *
         * یعنی یا مشتری نیامده و کسی «غیبت» نزده، یا کار شروع شده و
         * کسی ثبتش نکرده. هر دو حالت، موتور تخمین را کور می‌کند:
         * چیزی برای یاد گرفتن نمی‌ماند و وعدهٔ زمان بی‌اعتبار می‌شود.
         */
        $stale = DB::selectOne(
            "SELECT COUNT(*) AS c FROM appointments
              WHERE salon_id = ? AND status IN ('queued','confirmed','pending')
                AND scheduled_at IS NOT NULL
                AND scheduled_at < DATE_SUB(NOW(), INTERVAL 2 HOUR)
                AND DATE(scheduled_at) = ?",
            [$this->salonId, $date]
        );

        return [
            'unpaid' => (int) ($unpaid['c'] ?? 0),
            'no_shows' => (int) ($noShows['c'] ?? 0),
            'stale' => (int) ($stale['c'] ?? 0),
        ];
    }

    /** عددهای خودِ روز. */
    public function today(string $date): array
    {
        $money = DB::selectOne(
            'SELECT COALESCE(SUM(amount),0) AS total, COALESCE(SUM(tip_amount),0) AS tips, COUNT(*) AS count
               FROM payments WHERE salon_id = ? AND DATE(paid_at) = ?',
            [$this->salonId, $date]
        );

        $appts = DB::selectOne(
            "SELECT
                COUNT(*) AS total,
                SUM(status = 'completed') AS completed,
                SUM(status = 'cancelled') AS cancelled,
                SUM(status = 'no_show') AS no_show,
                SUM(kind = 'walkin') AS walkin
             FROM appointments
              WHERE salon_id = ?
                AND DATE(COALESCE(scheduled_at, queued_at, created_at)) = ?",
            [$this->salonId, $date]
        );

        $newCustomers = DB::selectOne(
            'SELECT COUNT(*) AS c FROM customers
              WHERE salon_id = ? AND deleted_at IS NULL AND DATE(created_at) = ?',
            [$this->salonId, $date]
        );

        return [
            'revenue' => (int) ($money['total'] ?? 0),
            'tips' => (int) ($money['tips'] ?? 0),
            'payments' => (int) ($money['count'] ?? 0),
            'appointments' => (int) ($appts['total'] ?? 0),
            'completed' => (int) ($appts['completed'] ?? 0),
            'cancelled' => (int) ($appts['cancelled'] ?? 0),
            'no_show' => (int) ($appts['no_show'] ?? 0),
            'walkin' => (int) ($appts['walkin'] ?? 0),
            'new_customers' => (int) ($newCustomers['c'] ?? 0),
        ];
    }

    /**
     * همین حالا داخل سالن چه خبر است.
     *
     * فقط برای امروز معنی دارد؛ اگر صاحب سالن تاریخ دیگری را ببیند
     * این بخش نمایش داده نمی‌شود، چون «الان» در گذشته بی‌معنی است.
     */
    public function rightNow(): array
    {
        $row = DB::selectOne(
            "SELECT
                SUM(status = 'in_chair') AS in_chair,
                SUM(status = 'queued') AS waiting
             FROM appointments WHERE salon_id = ?",
            [$this->salonId]
        );

        $last = DB::selectOne(
            "SELECT estimated_start_at FROM appointments
              WHERE salon_id = ? AND status = 'queued' AND estimated_start_at IS NOT NULL
              ORDER BY estimated_start_at DESC LIMIT 1",
            [$this->salonId]
        );

        return [
            'in_chair' => (int) ($row['in_chair'] ?? 0),
            'waiting' => (int) ($row['waiting'] ?? 0),
            'last_eta' => $last['estimated_start_at'] ?? null,
        ];
    }

    /** هر آرایشگر امروز چند نفر و چقدر. */
    public function byStaff(string $date): array
    {
        return DB::select(
            "SELECT st.id, st.name, st.color,
                    COUNT(a.id) AS done,
                    COALESCE(SUM(p.amount),0) AS total
               FROM staff st
               LEFT JOIN appointments a
                      ON a.staff_id = st.id AND a.status = 'completed'
                     AND DATE(a.actual_end_at) = ?
               LEFT JOIN payments p ON p.appointment_id = a.id
              WHERE st.salon_id = ? AND st.is_active = 1
              GROUP BY st.id, st.name, st.color
              ORDER BY total DESC, done DESC, st.sort_order",
            [$date, $this->salonId]
        );
    }

    /**
     * درآمد هفت روز گذشته، با روزهای خالی پر شده.
     *
     * پر کردن روزهای خالی مهم است: اگر فقط ردیف‌های دیتابیس را بکشیم،
     * نمودار روزِ تعطیل را حذف می‌کند و هفته کوتاه‌تر و پررونق‌تر از
     * واقعیت دیده می‌شود.
     *
     * @return list<array{date:string,total:int,label:string,weekday:string}>
     */
    public function lastDays(string $endDate, int $days = 7): array
    {
        $end = new DateTimeImmutable($endDate);
        $start = $end->modify('-' . ($days - 1) . ' days');

        $rows = DB::select(
            'SELECT DATE(paid_at) AS d, COALESCE(SUM(amount),0) AS total
               FROM payments WHERE salon_id = ? AND DATE(paid_at) BETWEEN ? AND ?
              GROUP BY DATE(paid_at)',
            [$this->salonId, $start->format('Y-m-d'), $end->format('Y-m-d')]
        );

        $byDate = [];
        foreach ($rows as $row) {
            $byDate[(string) $row['d']] = (int) $row['total'];
        }

        $out = [];
        for ($i = 0; $i < $days; ++$i) {
            $day = $start->modify('+' . $i . ' days');
            $key = $day->format('Y-m-d');
            [$jy, $jm, $jd] = Jalali::fromDateTime($day);

            $out[] = [
                'date' => $key,
                'total' => $byDate[$key] ?? 0,
                'label' => Jalali::toPersianDigits((string) $jd),
                'weekday' => Jalali::weekdayName($day),
            ];
        }

        return $out;
    }

    /**
     * جمع یک بازه، برای مقایسهٔ «این هفته با هفتهٔ قبل».
     *
     * درصدِ تنها گمراه‌کننده است، پس هر دو عدد خام هم برمی‌گردند و
     * ویو خودش تصمیم می‌گیرد چه بگوید.
     */
    public function rangeTotal(string $from, string $to): int
    {
        $row = DB::selectOne(
            'SELECT COALESCE(SUM(amount),0) AS total FROM payments
              WHERE salon_id = ? AND DATE(paid_at) BETWEEN ? AND ?',
            [$this->salonId, $from, $to]
        );

        return (int) ($row['total'] ?? 0);
    }

    /**
     * شلوغ‌ترین ساعت‌های سی روز گذشته.
     *
     * به درد تنظیم ساعت کاری و شیفت آرایشگرها می‌خورد: اگر ساعت ۱۹
     * سه برابر ساعت ۱۰ مشتری دارد، شیفت باید همان‌جا سنگین‌تر باشد.
     *
     * @return list<array{hour:int,count:int}>
     */
    public function busiestHours(string $endDate, int $days = 30): array
    {
        $start = (new DateTimeImmutable($endDate))->modify('-' . $days . ' days');

        $rows = DB::select(
            "SELECT HOUR(COALESCE(actual_start_at, scheduled_at, queued_at)) AS h, COUNT(*) AS c
               FROM appointments
              WHERE salon_id = ? AND status = 'completed'
                AND DATE(COALESCE(actual_start_at, scheduled_at, queued_at)) BETWEEN ? AND ?
              GROUP BY h ORDER BY h",
            [$this->salonId, $start->format('Y-m-d'), $endDate]
        );

        $out = [];
        foreach ($rows as $row) {
            if ($row['h'] === null) {
                continue;
            }
            $out[] = ['hour' => (int) $row['h'], 'count' => (int) $row['c']];
        }

        return $out;
    }

    /**
     * مشتری‌هایی که مدتی است نیامده‌اند.
     *
     * «مدتی» یعنی بیش از دو برابر فاصلهٔ معمولِ مراجعه‌شان — نه یک عدد
     * ثابت. کسی که ماهی یک بار می‌آید و دو ماه نیامده، جای نگرانی
     * دارد؛ کسی که سالی دو بار می‌آید و دو ماه نیامده، هنوز عادی است.
     *
     * فقط مشتریِ باسابقه (۳ مراجعه به بالا) شمرده می‌شود، چون برای
     * کسی که یک بار آمده هیچ «معمولی» وجود ندارد که ازش فاصله بگیرد.
     *
     * @return list<array>
     */
    public function sleepingCustomers(int $limit = 5): array
    {
        return DB::select(
            'SELECT c.id, c.name, c.phone, c.visit_count, c.last_visit_at,
                    DATEDIFF(NOW(), c.last_visit_at) AS days_away
               FROM customers c
              WHERE c.salon_id = ?
                AND c.deleted_at IS NULL
                AND c.visit_count >= 3
                AND c.last_visit_at IS NOT NULL
                AND DATEDIFF(NOW(), c.last_visit_at) >
                    2 * (DATEDIFF(c.last_visit_at, c.created_at) / GREATEST(c.visit_count - 1, 1))
                AND DATEDIFF(NOW(), c.last_visit_at) >= 21
              ORDER BY c.visit_count DESC, days_away DESC
              LIMIT ' . max(1, $limit),
            [$this->salonId]
        );
    }
}
