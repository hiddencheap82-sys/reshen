<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\View;
use PHPUnit\Framework\TestCase;

/**
 * آواتار حرف‌اول.
 *
 * دو چیز باید درست بماند: رنگِ هر نام همیشه همان بماند، و نام کاربر
 * به HTML تزریق نشود. اولی به این دلیل که اگر «مهدی احمدی» امروز آبی
 * و فردا سبز باشد، رنگ دیگر کمکی به پیدا کردنش نمی‌کند و فقط سر و صدا
 * می‌شود — و hash پیش‌فرض PHP دقیقاً همین کار را می‌کند، چون در هر
 * اجرا تصادفی‌سازی می‌شود.
 */
final class AvatarTest extends TestCase
{
    protected function setUp(): void
    {
        View::setBasePath(BASE_PATH . '/resources/views');
    }

    private function render(string $name, ?string $color = null): string
    {
        return View::render('components.avatar', ['name' => $name, 'avatarColor' => $color]);
    }

    public function testTheSameNameAlwaysGetsTheSameColour(): void
    {
        $first = $this->render('مهدی احمدی');

        for ($i = 0; $i < 5; ++$i) {
            $this->assertSame($first, $this->render('مهدی احمدی'));
        }
    }

    public function testItShowsTheFirstLetter(): void
    {
        $this->assertStringContainsString('>م<', $this->render('مهدی احمدی'));
        $this->assertStringContainsString('>A<', $this->render('Ali'));
    }

    /** نام خالی نباید خروجی را بشکند. */
    public function testAnEmptyNameFallsBackToAQuestionMark(): void
    {
        $html = $this->render('');

        $this->assertStringContainsString('؟', $html);
        $this->assertStringContainsString('background:#', $html);
    }

    public function testAnExplicitColourWins(): void
    {
        $this->assertStringContainsString('background:#2563eb', $this->render('رضا', '#2563eb'));
    }

    public function testTheNameIsEscaped(): void
    {
        $html = $this->render('<img src=x onerror=alert(1)>');

        $this->assertStringNotContainsString('<img', $html);
    }

    /**
     * هر شش رنگ با متن سفید از WCAG AA رد می‌شوند؟
     *
     * متن آواتار همیشه سفید است، پس رنگ پس‌زمینه تنها متغیر است. یک
     * رنگ روشنِ اضافه‌شده به پالت، حرف را ناخوانا می‌کند بی‌آنکه چیزی
     * خطا بدهد.
     */
    public function testEveryPaletteColourIsReadableWithWhiteText(): void
    {
        $seen = [];
        // نام‌های متنوع تا همهٔ خانه‌های پالت دیده شوند.
        foreach (range(1, 400) as $i) {
            preg_match('/background:(#[0-9A-Fa-f]{6})/', $this->render('نام' . $i), $m);
            $seen[$m[1]] = true;
        }

        $this->assertCount(12, $seen, 'پالت باید دوازده رنگ داشته باشد.');

        foreach (array_keys($seen) as $hex) {
            $this->assertGreaterThanOrEqual(
                4.5,
                self::contrastWithWhite($hex),
                "رنگ {$hex} با متن سفید از AA رد نمی‌شود."
            );
        }
    }

    /**
     * رنگ‌ها در یک فهرست واقعی پخش می‌شوند؟
     *
     * نسخهٔ اول ‎crc32() % 6‎ بود و از هشت مشتریِ فهرست، پنج‌تا یک‌رنگ
     * درمی‌آمدند — رنگ به‌جای کمک به پیدا کردن، فقط سر و صدا می‌شد.
     */
    public function testColoursSpreadAcrossARealisticList(): void
    {
        $names = [
            'محمد نوری', 'رضا صادقی', 'امین تهرانی', 'بهرام یزدی',
            'کاوه شریفی', 'مهدی احمدی', 'حسین رضایی', 'علی کریمی',
        ];

        $colours = [];
        foreach ($names as $name) {
            preg_match('/background:(#[0-9A-Fa-f]{6})/', $this->render($name), $m);
            $colours[] = $m[1];
        }

        $this->assertGreaterThanOrEqual(
            6,
            count(array_unique($colours)),
            'رنگ‌ها در یک فهرست هشت‌نفره خیلی تکرار می‌شوند.'
        );
        $this->assertLessThanOrEqual(
            2,
            max(array_count_values($colours)),
            'یک رنگ بیش از دو بار در فهرست هشت‌نفره تکرار شده.'
        );
    }

    private static function contrastWithWhite(string $hex): float
    {
        $channels = array_map(
            static fn (string $pair): float => hexdec($pair) / 255,
            str_split(ltrim($hex, '#'), 2)
        );
        $linear = array_map(
            static fn (float $v): float => $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4,
            $channels
        );
        $luminance = 0.2126 * $linear[0] + 0.7152 * $linear[1] + 0.0722 * $linear[2];

        return 1.05 / ($luminance + 0.05);
    }
}
