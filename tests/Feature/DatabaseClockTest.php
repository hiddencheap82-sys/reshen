<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\DB;
use PHPUnit\Framework\TestCase;

/**
 * ساعت MySQL و ساعت PHP یکی هستند؟
 *
 * چرا این تست هست: برنامه دو ساعت دارد و هیچ‌کدام خطا نمی‌دهند. PHP
 * روی ‎Asia/Tehran‎ تنظیم می‌شود (app/bootstrap.php)، ولی MySQL روی
 * هاست معمولاً UTC است — و آن‌وقت ‎NOW()‎ سه‌ساعت‌ونیم با ‎date()‎ فرق
 * می‌کند.
 *
 * هر کوئری‌ای که زمانِ نوشته‌شده با PHP را با ساعت MySQL می‌سنجد،
 * بی‌صدا جواب غلط می‌دهد. این با نوشتن محدودیتِ تلاشِ ورود پیدا شد:
 * پنجرهٔ ۱۵ دقیقه‌ای هیچ ردیفی را نمی‌دید، پس محدودیت *هرگز* فعال
 * نمی‌شد و رمز بی‌نهایت بار قابل حدس زدن بود. کد کاملاً درست به نظر
 * می‌رسید.
 *
 * سیزده جای دیگر هم از ساعت MySQL استفاده می‌کنند، از جمله حفاظ رزرو
 * و انقضای لینک ورود.
 */
final class DatabaseClockTest extends TestCase
{
    public function test_mysql_and_php_agree_on_the_time(): void
    {
        $row = DB::selectOne('SELECT NOW() AS db_now');
        $drift = abs(strtotime((string) $row['db_now']) - time());

        self::assertLessThanOrEqual(
            2,
            $drift,
            'ساعت MySQL و PHP ' . $drift . ' ثانیه اختلاف دارند. '
            . 'هر کوئری‌ای که NOW() را با زمانِ نوشته‌شدهٔ PHP بسنجد، غلط جواب می‌دهد.'
        );
    }

    public function test_mysql_and_php_agree_on_the_date(): void
    {
        $row = DB::selectOne('SELECT CURDATE() AS d');

        self::assertSame(date('Y-m-d'), (string) $row['d']);
    }

    /**
     * ردیفی که MySQL با ‎current_timestamp()‎ مهر می‌زند، از دید PHP
     * «الان» است — نه چند ساعت پیش یا بعد.
     *
     * این همان چیزی است که در عمل می‌شکست: ‎login_attempts.created_at‎
     * را MySQL می‌نوشت و PHP در پنجره‌اش پیدایش نمی‌کرد.
     */
    public function test_a_row_stamped_by_mysql_looks_current_to_php(): void
    {
        DB::statement('DELETE FROM login_attempts WHERE phone = ?', ['+989120000000']);
        DB::insert('login_attempts', ['phone' => '+989120000000', 'succeeded' => 0]);

        $since = date('Y-m-d H:i:s', time() - 60);
        $row = DB::selectOne(
            'SELECT COUNT(*) AS c FROM login_attempts WHERE phone = ? AND created_at > ?',
            ['+989120000000', $since]
        );

        self::assertSame(1, (int) $row['c'], 'ردیفی که همین الان ساخته شد، در پنجرهٔ یک‌دقیقه‌ای دیده نشد.');

        DB::statement('DELETE FROM login_attempts WHERE phone = ?', ['+989120000000']);
    }
}
