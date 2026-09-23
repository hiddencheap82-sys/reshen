<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\DB;
use App\Core\Scheduler;
use PHPUnit\Framework\TestCase;

/**
 * زمان‌بندِ درخواست‌محور — جایگزین کرون.
 *
 * چرا این تست هست: کرون حذف شد چون راه‌اندازی‌اش بار اضافه بود و
 * نبودنش هیچ صدایی نداشت. جایگزینش نباید همان اشتباه را با شکل تازه
 * تکرار کند — و دو خرابیِ بی‌صدا در کمینش است:
 *
 * ۱. **اجرای تکراری.** هر درخواست وب زمان‌بند را تحریک می‌کند، و در
 *    ساعت شلوغی ده‌ها درخواست هم‌زمان می‌آیند. بدون قفل، یادآورها
 *    چند بار ارسال می‌شدند — به مشتری، با پول واقعی.
 *
 * ۲. **گیر کردن برای همیشه.** اگر پروسه وسط کار بمیرد (تایم‌اوت PHP
 *    روی هاست اشتراکی)، قفل باید خودش باز شود وگرنه آن کار دیگر
 *    هرگز اجرا نمی‌شود و هیچ‌کس نمی‌فهمد.
 */
final class SchedulerTest extends TestCase
{
    protected function setUp(): void
    {
        DB::statement('DELETE FROM scheduled_tasks WHERE 1');
    }

    protected function tearDown(): void
    {
        DB::statement('DELETE FROM scheduled_tasks WHERE 1');
    }

    public function test_it_runs_every_task_on_a_fresh_install(): void
    {
        $report = Scheduler::runDue();

        self::assertNotEmpty($report, 'روی نصب تازه باید همهٔ کارها اجرا شوند.');

        foreach ($report as $task => $result) {
            self::assertStringNotContainsString('خطا', $result, "کار «{$task}» خطا داد: {$result}");
        }
    }

    /** بلافاصله بعد از اجرا، هیچ کاری سررسید نیست. */
    public function test_a_task_does_not_run_again_before_its_interval(): void
    {
        Scheduler::runDue();

        self::assertSame([], Scheduler::runDue(), 'کار پیش از رسیدن فاصله دوباره اجرا شد.');
    }

    public function test_force_ignores_the_interval(): void
    {
        Scheduler::runDue();

        self::assertNotEmpty(Scheduler::runDue(true));
    }

    /**
     * قفل، کار را برای اجرای دوم نگه می‌دارد.
     *
     * شبیه‌سازیِ درخواستِ هم‌زمان: ردیف قفل‌شده است و اجرای بعدی
     * نباید بتواند بگیردش.
     */
    public function test_a_locked_task_is_skipped(): void
    {
        Scheduler::runDue();

        // همه سررسید، ولی همه قفل
        DB::statement(
            'UPDATE scheduled_tasks
                SET last_run_at = (NOW() - INTERVAL 1 DAY),
                    locked_until = (NOW() + INTERVAL 60 SECOND)'
        );

        self::assertSame([], Scheduler::runDue(), 'کارِ قفل‌شده اجرا شد — یعنی ارسال تکراری ممکن است.');
    }

    /** قفلِ منقضی مانع اجرا نیست — وگرنه کار برای همیشه گیر می‌کند. */
    public function test_an_expired_lock_does_not_block_forever(): void
    {
        Scheduler::runDue();

        DB::statement(
            'UPDATE scheduled_tasks
                SET last_run_at = (NOW() - INTERVAL 1 DAY),
                    locked_until = (NOW() - INTERVAL 10 MINUTE)'
        );

        self::assertNotEmpty(Scheduler::runDue(), 'قفلِ منقضی هنوز جلوی اجرا را می‌گیرد.');
    }

    public function test_it_records_when_each_task_last_ran(): void
    {
        Scheduler::runDue();

        $rows = Scheduler::status();
        self::assertNotEmpty($rows);

        foreach ($rows as $row) {
            self::assertNotNull($row['last_run_at'], "کار «{$row['name']}» زمان اجرا ثبت نکرد.");
            self::assertNull($row['last_error'], "کار «{$row['name']}» خطا ثبت کرد: {$row['last_error']}");
            self::assertSame(1, (int) $row['run_count']);
        }
    }

    public function test_last_run_at_is_null_before_anything_runs(): void
    {
        self::assertNull(Scheduler::lastRunAt());
    }

    public function test_last_run_at_is_set_after_running(): void
    {
        Scheduler::runDue();

        $at = Scheduler::lastRunAt();
        self::assertIsInt($at);
        self::assertLessThanOrEqual(5, abs(time() - $at), 'زمان ثبت‌شده با ساعت برنامه نمی‌خواند.');
    }

    /**
     * کرون واقعاً رفته؟
     *
     * اگر فایل‌های ورودی‌اش برگردند، دو سازوکار موازی می‌شوند و
     * یادآورها دو بار ارسال می‌شوند.
     */
    public function test_the_old_cron_entry_points_are_gone(): void
    {
        foreach (['/public/cron.php', '/tools/cron.php', '/app/Core/Cron.php'] as $path) {
            self::assertFileDoesNotExist(BASE_PATH . $path, "«{$path}» برگشته است.");
        }
    }

    /** زمان‌بند باید از index.php تحریک شود، وگرنه هیچ‌وقت اجرا نمی‌شود. */
    public function test_the_front_controller_kicks_the_scheduler(): void
    {
        $index = (string) file_get_contents(BASE_PATH . '/public/index.php');

        self::assertStringContainsString('Scheduler::kickAfterResponse()', $index);

        // باید *بعد* از فرستادن پاسخ باشد، وگرنه صفحه کند می‌شود.
        self::assertGreaterThan(
            strpos($index, '$response->send()'),
            strpos($index, 'Scheduler::kickAfterResponse()'),
            'زمان‌بند پیش از ارسال پاسخ صدا زده می‌شود؛ کاربر منتظر کارِ پس‌زمینه می‌ماند.'
        );
    }
}
