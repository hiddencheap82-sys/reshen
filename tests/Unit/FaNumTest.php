<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * عدد فارسی، از جمله اعشار.
 *
 * چرا این تست هست: امضای ‎fa_num‎ فقط ‎int|string‎ بود، و دو سنجهٔ
 * کلیدیِ پنل پلتفرم float‌اند — «خطای تخمین» و «نرخ ثبت پایان» هر دو
 * با ‎round($x, 1)‎ حساب می‌شوند.
 *
 * نتیجه: PHP بی‌سروصدا به int تبدیل می‌کرد و ۹۸٫۲ می‌شد ۹۸. دقتی که
 * کد عمداً حساب کرده بود، سرِ راهِ نمایش دور ریخته می‌شد. روی PHP 8.1
 * یک ‎Deprecated‎ هم می‌داد که در حالت دیباگ وسط صفحه چاپ می‌شد و
 * چیدمان را ۵۱۲ پیکسل بیرون می‌زد — تنها نشانهٔ بیرونی‌اش همین بود.
 */
final class FaNumTest extends TestCase
{
    public static function numbers(): array
    {
        return [
            'عدد صحیح' => [7, '۷'],
            'صفر' => [0, '۰'],
            'رشته' => ['۱۲', '۱۲'],
            'اعشار یک‌رقمی' => [98.2, '۹۸٫۲'],
            'اعشار با صفر انتهایی' => [98.0, '۹۸'],
            'درصد' => [12.5, '۱۲٫۵'],
            'صد' => [100.0, '۱۰۰'],
            'اعشار بلند' => [3.14159, '۳٫۱۴'],
            'منفی' => [-4.5, '-۴٫۵'],
        ];
    }

    /** @dataProvider numbers */
    #[\PHPUnit\Framework\Attributes\DataProvider('numbers')]
    public function testItFormats(int|float|string $input, string $expected): void
    {
        $this->assertSame($expected, fa_num($input));
    }

    /** ★ خودِ باگ: اعشار نباید بریده شود. */
    public function testADecimalIsNotSilentlyTruncated(): void
    {
        $this->assertStringContainsString('٫', fa_num(98.2), 'اعشار حذف شد.');
        $this->assertNotSame(fa_num(98.0), fa_num(98.2), '۹۸٫۲ و ۹۸ یکسان نمایش داده می‌شوند.');
    }

    /** جداکنندهٔ اعشار فارسی است، نه نقطهٔ لاتین. */
    public function testTheDecimalSeparatorIsPersian(): void
    {
        $this->assertStringNotContainsString('.', fa_num(12.5));
    }

    /**
     * هیچ صفحه‌ای نباید float را با cast به int به fa_num بدهد.
     *
     * اگر کسی برای ساکت کردن هشدار ‎(int)‎ بگذارد، همان باگ برمی‌گردد
     * — فقط بی‌صداتر.
     */
    public function testNoViewCastsAFloatMetricToInt(): void
    {
        $offenders = [];
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(BASE_PATH . '/resources/views')
        );

        foreach ($files as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $body = (string) file_get_contents($file->getPathname());

            foreach (['mae_minutes', 'end_registration_rate'] as $metric) {
                if (preg_match('~fa_num\(\s*\(int\)[^)]*' . $metric . '~', $body) === 1) {
                    $offenders[] = basename($file->getPathname()) . " → {$metric}";
                }
            }
        }

        $this->assertSame([], $offenders, 'سنجهٔ اعشاری با cast به int نمایش داده می‌شود.');
    }
}
