<?php

declare(strict_types=1);

namespace App\Domain\Platform;

use App\Core\DB;

/**
 * چه چیزی در سالن‌ها خراب است.
 *
 * چرا این کلاس هست: پنل پلتفرم می‌توانست بگوید چند سالن داریم و چند
 * نوبت ثبت شده، ولی نمی‌توانست بگوید **کدام سالن همین الان کار
 * نمی‌کند**. و بدترین حالت‌ها آن‌هایی‌اند که هیچ خطایی نمی‌دهند:
 * سالنی که آرایشگر فعال ندارد صفحهٔ عمومی‌اش باز می‌شود، خوشگل هم
 * هست، فقط هیچ سانسی ندارد. مشتری فکر می‌کند پر است و می‌رود. صاحب
 * سالن فکر می‌کند کسی نمی‌آید.
 *
 * ترتیب اهمیت عمدی است:
 *
 *   BLOCKING — مشتری *نمی‌تواند* نوبت بگیرد. تا این درست نشود، سالن
 *              عملاً وجود ندارد.
 *   WARNING  — کار می‌کند ولی چیزی دارد بد پیش می‌رود: پول، پیامک،
 *              یا دقتِ تخمین.
 *   INFO     — دانستنش خوب است، فوریتی ندارد.
 *
 * همه با یک کوئری حساب می‌شوند نه یکی به ازای هر سالن. با پنجاه
 * سالن، حلقهٔ N+1 این صفحه را به چند صد کوئری می‌رساند — و این دقیقاً
 * صفحه‌ای است که وقتی اوضاع خراب است باز می‌شود.
 */
final class SalonHealth
{
    public const BLOCKING = 'blocking';
    public const WARNING = 'warning';
    public const INFO = 'info';

    /** ترتیب نمایش: بدترین اول. */
    private const RANK = [self::BLOCKING => 0, self::WARNING => 1, self::INFO => 2];

    /**
     * هر سالن با ایرادهایش.
     *
     * @param bool $onlyBroken فقط سالن‌هایی که چیزی دارند
     * @return array<int,array{salon:array,issues:array<int,array{level:string,title:string,fix:string}>,worst:?string}>
     */
    public function all(bool $onlyBroken = false): array
    {
        $out = [];

        foreach ($this->rows() as $row) {
            $issues = $this->issuesFor($row);

            if ($onlyBroken && $issues === []) {
                continue;
            }

            $out[] = [
                'salon' => $row,
                'issues' => $issues,
                'worst' => $issues === [] ? null : $issues[0]['level'],
            ];
        }

        usort($out, static function (array $a, array $b): int {
            $ra = $a['worst'] === null ? 9 : self::RANK[$a['worst']];
            $rb = $b['worst'] === null ? 9 : self::RANK[$b['worst']];

            return $ra <=> $rb ?: count($b['issues']) <=> count($a['issues']);
        });

        return $out;
    }

    /** ایرادهای یک سالن، برای صفحهٔ خودش. */
    public function forSalon(int $salonId): array
    {
        foreach ($this->rows($salonId) as $row) {
            return $this->issuesFor($row);
        }

        return [];
    }

    /**
     * شمارش برای صفحهٔ نخست: چند سالن از کار افتاده، چند تا هشدار دارد.
     *
     * @return array{blocking:int,warning:int,healthy:int}
     */
    public function summary(): array
    {
        $count = ['blocking' => 0, 'warning' => 0, 'healthy' => 0];

        foreach ($this->all() as $entry) {
            if ($entry['worst'] === self::BLOCKING) {
                $count['blocking']++;
            } elseif ($entry['worst'] === self::WARNING) {
                $count['warning']++;
            } else {
                $count['healthy']++;
            }
        }

        return $count;
    }

    /**
     * یک کوئری، همهٔ شمارنده‌ها.
     *
     * @return array<int,array>
     */
    private function rows(?int $salonId = null): array
    {
        $sql = "SELECT s.id, s.name, s.slug, s.city, s.is_active, s.plan_code,
                       s.trial_ends_at, s.created_at,
                       (SELECT COUNT(*) FROM staff st
                         WHERE st.salon_id = s.id AND st.is_active = 1) AS active_staff,
                       (SELECT COUNT(*) FROM services sv
                         WHERE sv.salon_id = s.id AND sv.is_active = 1) AS active_services,
                       -- ساعت کاریِ خودِ سالن (نه ساعت اختصاصی آرایشگر)
                       (SELECT COUNT(*) FROM working_hours wh
                         WHERE wh.salon_id = s.id
                           AND wh.staff_id IS NULL
                           AND wh.is_closed = 0) AS open_days,
                       (SELECT COUNT(*) FROM salon_user su
                         WHERE su.salon_id = s.id
                           AND su.is_active = 1
                           AND su.role IN ('owner','manager')) AS managers,
                       (SELECT MAX(a.created_at) FROM appointments a
                         WHERE a.salon_id = s.id) AS last_booking_at,
                       (SELECT COUNT(*) FROM appointments a
                         WHERE a.salon_id = s.id
                           AND a.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) AS bookings_30d,
                       (SELECT COUNT(*) FROM appointments a
                         WHERE a.salon_id = s.id
                           AND a.status = 'completed'
                           AND a.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) AS completed_30d,
                       (SELECT COUNT(*) FROM appointments a
                         WHERE a.salon_id = s.id
                           AND a.status = 'no_show'
                           AND a.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) AS no_show_30d,
                       (SELECT COUNT(*) FROM sms_messages m
                         WHERE m.salon_id = s.id
                           AND m.status = 'failed'
                           AND m.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) AS sms_failed_7d,
                       (SELECT COUNT(*) FROM platform_invoices i
                         WHERE i.salon_id = s.id
                           AND i.status IN ('pending','overdue')
                           AND i.period_end < CURDATE()) AS unpaid_invoices
                  FROM salons s";

        $args = [];
        if ($salonId !== null) {
            $sql .= ' WHERE s.id = ?';
            $args[] = $salonId;
        }

        $sql .= ' ORDER BY s.name';

        return DB::select($sql, $args);
    }

    /**
     * @param array<string,mixed> $r
     * @return array<int,array{level:string,title:string,fix:string}>
     */
    private function issuesFor(array $r): array
    {
        $issues = [];
        $active = (int) $r['is_active'] === 1;

        /*
         * سه ایرادِ کُشنده. هر کدام به‌تنهایی یعنی `SlotFinder` هیچ
         * سانسی برنمی‌گرداند و صفحهٔ عمومیِ سالن خالی است — بدون هیچ
         * پیام خطایی، که بدترین بخشش است.
         *
         * فقط برای سالن فعال می‌سنجیم: سالنِ بسته‌شده عمداً بسته است.
         */
        if ($active) {
            if ((int) $r['active_staff'] === 0) {
                $issues[] = [
                    'level' => self::BLOCKING,
                    'title' => 'هیچ آرایشگر فعالی ندارد',
                    'fix' => 'پنل سالن → آرایشگرها → افزودن. بدون آرایشگر، سانسی ساخته نمی‌شود و صفحهٔ رزرو خالی می‌ماند.',
                ];
            }

            if ((int) $r['active_services'] === 0) {
                $issues[] = [
                    'level' => self::BLOCKING,
                    'title' => 'هیچ خدمت فعالی ندارد',
                    'fix' => 'پنل سالن → خدمات → افزودن. مشتری باید بتواند خدمتی انتخاب کند، وگرنه گام دوم رزرو بن‌بست است.',
                ];
            }

            if ((int) $r['open_days'] === 0) {
                $issues[] = [
                    'level' => self::BLOCKING,
                    'title' => 'هیچ روز بازی ندارد',
                    'fix' => 'پنل سالن → تنظیمات → ساعت کاری. یا همهٔ روزها تعطیل خورده‌اند، یا ساعت کاری اصلاً ثبت نشده.',
                ];
            }
        }

        if ((int) $r['managers'] === 0) {
            $issues[] = [
                'level' => self::BLOCKING,
                'title' => 'صاحب یا مدیری ندارد',
                'fix' => 'هیچ‌کس نمی‌تواند وارد پنل این سالن شود. از «کاربران» یک حساب بسازید و اینجا وصلش کنید.',
            ];
        }

        // ── هشدارها ──────────────────────────────────────────────

        if ($active && $r['trial_ends_at'] !== null
            && (string) $r['plan_code'] === 'trial'
            && strtotime((string) $r['trial_ends_at']) < time()) {
            $issues[] = [
                'level' => self::WARNING,
                'title' => 'دورهٔ آزمایش تمام شده',
                'fix' => 'پلن را عوض کنید یا مهلت را تمدید کنید. تا وقتی دست نخورده، سالن رایگان کار می‌کند.',
            ];
        }

        if ((int) $r['unpaid_invoices'] > 0) {
            $issues[] = [
                'level' => self::WARNING,
                'title' => fa_num((int) $r['unpaid_invoices']) . ' صورتحساب پرداخت‌نشده از دوره‌های گذشته',
                'fix' => 'صفحهٔ صورتحساب‌ها. دوره‌اش تمام شده و هنوز «پرداخت شد» نخورده.',
            ];
        }

        if ((int) $r['sms_failed_7d'] > 0) {
            $issues[] = [
                'level' => self::WARNING,
                'title' => fa_num((int) $r['sms_failed_7d']) . ' پیامک ناموفق در هفتهٔ گذشته',
                'fix' => 'صفحهٔ پیامک، بخش خطاها. معمولاً یعنی الگو تأیید نشده یا حساب اپراتور تمام شده.',
            ];
        }

        /*
         * سالنِ ساکت.
         *
         * سالنی که هیچ‌وقت نوبتی نداشته با سالنی که داشته و قطع شده
         * فرق دارد: اولی هنوز راه نیفتاده، دومی دارد می‌رود. هر دو
         * باید دیده شوند، ولی جمله‌شان یکی نیست.
         */
        if ($active) {
            $last = $r['last_booking_at'] === null ? null : strtotime((string) $r['last_booking_at']);
            $ageDays = (int) floor((time() - strtotime((string) $r['created_at'])) / 86400);

            if ($last === null && $ageDays >= 3) {
                $issues[] = [
                    'level' => self::WARNING,
                    'title' => 'هنوز هیچ نوبتی ثبت نکرده',
                    'fix' => fa_num($ageDays) . ' روز است ساخته شده. احتمالاً راه‌اندازی نیمه‌کاره مانده — یک تماس.',
                ];
            } elseif ($last !== null && $last < strtotime('-14 days')) {
                $issues[] = [
                    'level' => self::WARNING,
                    'title' => fa_num((int) floor((time() - $last) / 86400)) . ' روز است نوبتی ثبت نکرده',
                    'fix' => 'قبلاً کار می‌کرد و قطع شده. این تنها هشداری است که پیش از لغو اشتراک به دست می‌آید.',
                ];
            }
        }

        /*
         * نرخ ثبت پایان.
         *
         * دکمهٔ «تمام شد» چیزی است که موتور تخمین از آن یاد می‌گیرد.
         * آرایشگری که نمی‌زندش، سیستم را کور می‌کند: زمان انتظاری که
         * به مشتری‌ها می‌گوییم دیگر به واقعیت وصل نیست. این ایراد در
         * هیچ لاگی نمی‌افتد.
         */
        $decided = (int) $r['completed_30d'] + (int) $r['no_show_30d'];
        if ((int) $r['bookings_30d'] >= 10 && $decided > 0) {
            $rate = (int) $r['completed_30d'] / max(1, (int) $r['bookings_30d']) * 100;

            if ($rate < 50) {
                $issues[] = [
                    'level' => self::WARNING,
                    'title' => 'فقط ' . fa_num(round($rate)) . '٪ نوبت‌ها «تمام شد» خورده',
                    'fix' => 'آرایشگر دکمهٔ «تمام شد» را نمی‌زند. بدون آن، تخمین زمان انتظار از واقعیت دور می‌شود.',
                ];
            }
        }

        if ((int) $r['bookings_30d'] >= 10) {
            $noShowRate = (int) $r['no_show_30d'] / (int) $r['bookings_30d'] * 100;

            if ($noShowRate > 20) {
                $issues[] = [
                    'level' => self::INFO,
                    'title' => fa_num(round($noShowRate)) . '٪ غیبت در ۳۰ روز گذشته',
                    'fix' => 'بالاتر از حد معمول. شاید یادآورها نمی‌روند — صفحهٔ پیامک را ببینید.',
                ];
            }
        }

        if (!$active) {
            $issues[] = [
                'level' => self::INFO,
                'title' => 'غیرفعال شده',
                'fix' => 'صفحهٔ عمومی‌اش بسته است و مشتری نمی‌تواند نوبت بگیرد. داده‌اش دست‌نخورده مانده.',
            ];
        }

        usort($issues, static fn (array $a, array $b): int => self::RANK[$a['level']] <=> self::RANK[$b['level']]);

        return $issues;
    }
}
