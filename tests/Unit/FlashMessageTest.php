<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Session;
use App\Core\View;
use PHPUnit\Framework\TestCase;

/**
 * پیام لحظه‌ای هیچ‌جا گم نمی‌شود؟
 *
 * چرا این تست هست: پیام flash تنها چیزی در برنامه است که *یک بار*
 * خوانده می‌شود و بعد نیست. اگر لایه‌ای رندرش نکند، پیام بی‌صدا مصرف
 * می‌شود و کاربر هیچ‌وقت نمی‌فهمد چه شد — نه خطایی، نه لاگی.
 *
 * دقیقاً همین شده بود: layouts/auth.php هیچ flashی رندر نمی‌کرد، و
 * «دسترسی به این سالن ندارید» که کنترلر به ‎/salons‎ می‌فرستاد هیچ‌وقت
 * دیده نشد. کاربر روی سالن کلیک می‌کرد، به همان فهرست برمی‌گشت، و
 * فکر می‌کرد کلیکش ثبت نشده.
 */
final class FlashMessageTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
        // بوت‌استرپ تست لایهٔ HTTP را بالا نمی‌آورد، پس مسیر ویوها ست نشده.
        View::setBasePath(BASE_PATH . '/resources/views');
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    /** @return list<string> */
    private static function layouts(): array
    {
        $out = [];
        foreach (glob(BASE_PATH . '/resources/views/layouts/*.php') ?: [] as $path) {
            $out[] = $path;
        }

        return $out;
    }

    public function testEveryLayoutRendersFlashMessages(): void
    {
        $layouts = self::layouts();
        $this->assertGreaterThanOrEqual(5, count($layouts), 'لایه‌ها پیدا نشدند.');

        foreach ($layouts as $path) {
            $this->assertStringContainsString(
                'components/flash.php',
                (string) file_get_contents($path),
                basename($path) . ' پیام لحظه‌ای را رندر نمی‌کند؛ هر پیامی که به صفحه‌های '
                . 'این لایه فرستاده شود بی‌صدا مصرف و گم می‌شود.'
            );
        }
    }

    public function testNoViewRendersItsOwnFlashBlockAnyMore(): void
    {
        $offenders = [];
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(BASE_PATH . '/resources/views')
        );

        foreach ($files as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            // خودِ مؤلفه و کنترلرهایی که عمداً پیام را به ویو پاس می‌دهند، مستثنا.
            if (basename($file->getPathname()) === 'flash.php') {
                continue;
            }
            $body = (string) file_get_contents($file->getPathname());
            if (preg_match("/flash\('(success|error)'\)/", $body) === 1) {
                $offenders[] = basename($file->getPathname());
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'این ویوها خودشان flash را می‌خوانند. چون flash با خواندن مصرف می‌شود، '
            . 'لایه بعدش چیزی پیدا نمی‌کند و پیام دوبار یا اصلاً نمایش داده نمی‌شود.'
        );
    }

    public function testSuccessRendersWithItsOwnToneAndRole(): void
    {
        Session::flash('success', 'کار انجام شد.');
        $html = View::render('components.flash');

        $this->assertStringContainsString('کار انجام شد.', $html);
        $this->assertStringContainsString('flash-success', $html);
        $this->assertStringContainsString('role="status"', $html);
        $this->assertStringNotContainsString('flash-error', $html);
    }

    public function testErrorRendersWithItsOwnToneAndRole(): void
    {
        Session::flash('error', 'نشد.');
        $html = View::render('components.flash');

        $this->assertStringContainsString('نشد.', $html);
        $this->assertStringContainsString('flash-error', $html);
        $this->assertStringContainsString('role="alert"', $html);
    }

    /** خطا بالای موفقیت می‌نشیند: آن که کار را متوقف کرده مهم‌تر است. */
    public function testErrorComesFirstWhenBothArePresent(): void
    {
        Session::flash('success', 'ذخیره شد.');
        Session::flash('error', 'ولی پیامک نرفت.');
        $html = View::render('components.flash');

        $this->assertLessThan(
            strpos($html, 'flash-success'),
            strpos($html, 'flash-error'),
            'خطا باید اول بیاید.'
        );
    }

    public function testNothingIsRenderedWithoutAMessage(): void
    {
        $this->assertSame('', trim(View::render('components.flash')));
    }

    /** پیام کاربر به HTML تزریق نمی‌شود. */
    public function testTheMessageIsEscaped(): void
    {
        Session::flash('error', '<script>alert(1)</script>');
        $html = View::render('components.flash');

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }
}
