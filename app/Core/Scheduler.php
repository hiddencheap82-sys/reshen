<?php

declare(strict_types=1);

namespace App\Core;

use App\Domain\Messaging\QueueNotificationService;
use Throwable;

/**
 * کارهای دوره‌ای، بدون کرون.
 *
 * ── چرا کرون حذف شد ──
 *
 * راه‌اندازی‌اش بار اضافه‌ای بود که صاحب آرایشگاه باید در cPanel
 * انجام می‌داد، و نبودنش هیچ صدایی نداشت: اگر تنظیمش نمی‌کرد،
 * یادآورها فقط نمی‌رفتند. راهنما مجبور بود بنویسد «این را رد نکنید»،
 * که نشانهٔ طراحیِ شکننده است نه هشدار خوب.
 *
 * ── چطور کار می‌کند ──
 *
 * هر درخواست وب، *بعد از اینکه پاسخ کاربر رفت*، از این کلاس می‌پرسد
 * کاری سررسید شده یا نه. اگر آره، همان‌جا اجرا می‌شود.
 *
 * پنل صف هر ۱۵ ثانیه خودش را تازه می‌کند، پس تا وقتی سالن باز است
 * ضربانِ مطمئنی وجود دارد — و کارهای زمان‌بندی‌شده هم دقیقاً دربارهٔ
 * همان ساعت‌ها هستند.
 *
 * ── بهایش، صریح ──
 *
 * **اگر هیچ‌کس هیچ صفحه‌ای را باز نکند، هیچ کاری اجرا نمی‌شود.**
 *
 * برای این محصول قابل قبول است، ولی رایگان نیست: یادآورِ ۲۴ساعته‌ای
 * که باید روز تعطیلِ سالن برود، ممکن است تا اولین بازدید صبح بعد
 * عقب بیفتد. به همین دلیل خودِ یادآورها «سررسیدی» شده‌اند نه
 * «پنجره‌ای» — اجرای دیر هنوز ارسال می‌کند، فقط دیرتر.
 */
final class Scheduler
{
    /**
     * بیشترین زمانی که یک کار می‌تواند قفل را نگه دارد.
     *
     * اگر پروسه وسط کار بمیرد (تایم‌اوت PHP روی هاست اشتراکی)، قفل
     * باید خودش باز شود وگرنه آن کار تا ابد اجرا نمی‌شود.
     */
    private const LOCK_SECONDS = 120;

    private static bool $kicked = false;

    /**
     * کارها و فاصلهٔ اجرایشان.
     *
     * فاصله‌ها عمداً فرق دارند: یادآور باید تند باشد چون دیر رفتنش
     * یعنی مشتری نیامدن، ولی پاک‌سازی کدهای منقضی روزی یک بار هم
     * زیاد است.
     *
     * @return array<string,array{0:int,1:callable():string}>
     */
    private static function tasks(): array
    {
        return [
            'یادآورهای نوبت' => [60, static function (): string {
                $sent = (new QueueNotificationService())->sendUpcomingReminders();

                return "{$sent} پیامک";
            }],

            'بستن نوبت‌های رهاشده' => [900, static function (): string {
                // قاعده و دلیلش در خودِ LeftoverCloser است.
                $closed = (new \App\Domain\Queue\LeftoverCloser())->run();

                return "{$closed['no_show']} غیبت، {$closed['completed']} صندلیِ جامانده";
            }],

            'پاک‌سازی' => [3600, static function (): string {
                $codes = DB::statement(
                    'DELETE FROM otp_codes WHERE expires_at < (NOW() - INTERVAL 1 DAY)'
                )->rowCount();

                $attempts = DB::statement(
                    'DELETE FROM login_attempts WHERE created_at < (NOW() - INTERVAL 30 DAY)'
                )->rowCount();

                return "{$codes} کد، {$attempts} تلاش ورود";
            }],

            'صورتحساب‌های معوق' => [3600, static function (): string {
                (new \App\Domain\Platform\InvoiceRepository())->markOverdue();

                return 'بررسی شد';
            }],
        ];
    }

    /**
     * بعد از فرستادن پاسخ صدا زده می‌شود.
     *
     * هیچ‌وقت نباید درخواست کاربر را کند کند، پس:
     *   • فقط وقتی اجرا می‌شود که پاسخ رفته باشد
     *   • هر خطایی بلعیده می‌شود — کارِ پس‌زمینه حق ندارد صفحه را
     *     بشکند
     *   • در یک درخواست بیش از یک بار اجرا نمی‌شود
     */
    public static function kickAfterResponse(): void
    {
        if (self::$kicked || PHP_SAPI === 'cli') {
            return;
        }
        self::$kicked = true;

        /*
         * اتصال کاربر را می‌بندیم و بعد کار می‌کنیم.
         *
         * روی PHP-FPM (رایج‌ترین حالت cPanel) این تابع هست و پاسخ
         * همان لحظه می‌رود. روی بقیهٔ SAPIها فقط بافر را خالی
         * می‌کنیم؛ کاربر ممکن است چند صد میلی‌ثانیه بیشتر منتظر
         * بماند، ولی صفحه‌اش کامل رسیده.
         */
        if (function_exists('fastcgi_finish_request')) {
            @fastcgi_finish_request();
        } else {
            @ignore_user_abort(true);
            while (ob_get_level() > 0) {
                @ob_end_flush();
            }
            @flush();
        }

        try {
            self::runDue();
        } catch (Throwable $e) {
            error_log('[scheduler] ' . $e->getMessage());
        }
    }

    /**
     * کارهایی که سررسیدشان رسیده را اجرا می‌کند.
     *
     * @return array<string,string> نام کار => نتیجه
     */
    public static function runDue(bool $force = false): array
    {
        $report = [];

        foreach (self::tasks() as $name => [$everySeconds, $task]) {
            if (!self::claim($name, $force ? 0 : $everySeconds)) {
                continue;
            }

            $started = microtime(true);
            try {
                $result = $task();
                $ms = (int) round((microtime(true) - $started) * 1000);
                self::release($name, "{$result} ({$ms}ms)", null);
                $report[$name] = "{$result} ({$ms}ms)";
            } catch (Throwable $e) {
                self::release($name, null, $e->getMessage());
                $report[$name] = 'خطا: ' . $e->getMessage();
                error_log("[scheduler] {$name}: " . $e->getMessage());
            }
        }

        return $report;
    }

    /**
     * قفل را می‌گیرد — اگر سررسید شده و کس دیگری مشغولش نیست.
     *
     * یک UPDATE اتمیک است، نه «بخوان بعد بنویس»: دو درخواست هم‌زمان
     * هر دو شرط را درست می‌بینند، ولی فقط یکی‌شان ردیف را عوض می‌کند
     * و rowCount دیگری صفر می‌شود.
     */
    private static function claim(string $name, int $everySeconds): bool
    {
        // ردیف باید وجود داشته باشد تا UPDATE چیزی برای قفل کردن بیابد.
        DB::statement(
            'INSERT IGNORE INTO scheduled_tasks (name) VALUES (?)',
            [$name]
        );

        $affected = DB::statement(
            'UPDATE scheduled_tasks
                SET locked_until = (NOW() + INTERVAL ? SECOND)
              WHERE name = ?
                AND (locked_until IS NULL OR locked_until < NOW())
                AND (last_run_at IS NULL OR last_run_at <= (NOW() - INTERVAL ? SECOND))',
            [self::LOCK_SECONDS, $name, $everySeconds]
        )->rowCount();

        return $affected === 1;
    }

    private static function release(string $name, ?string $result, ?string $error): void
    {
        DB::statement(
            'UPDATE scheduled_tasks
                SET last_run_at = NOW(),
                    last_result = ?,
                    last_error = ?,
                    locked_until = NULL,
                    run_count = run_count + 1
              WHERE name = ?',
            [
                $result === null ? null : mb_substr($result, 0, 255),
                $error === null ? null : mb_substr($error, 0, 255),
                $name,
            ]
        );
    }

    /**
     * آخرین باری که *هر* کاری اجرا شد.
     *
     * صفحهٔ سلامت با این می‌فهمد زمان‌بند زنده است یا نه.
     */
    public static function lastRunAt(): ?int
    {
        try {
            $row = DB::selectOne('SELECT MAX(last_run_at) AS t FROM scheduled_tasks');
        } catch (Throwable $e) {
            return null;
        }

        $value = $row['t'] ?? null;

        return $value === null ? null : strtotime((string) $value);
    }

    /** وضعیت همهٔ کارها — برای صفحهٔ سلامت و پنل پلتفرم. */
    public static function status(): array
    {
        try {
            $rows = DB::select('SELECT * FROM scheduled_tasks ORDER BY name');
        } catch (Throwable $e) {
            return [];
        }

        $known = array_keys(self::tasks());
        $out = [];

        foreach ($rows as $row) {
            if (!in_array((string) $row['name'], $known, true)) {
                continue; // کارِ حذف‌شده از نسخهٔ قبلی
            }
            $out[] = $row;
        }

        return $out;
    }
}
