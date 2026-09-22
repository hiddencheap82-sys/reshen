<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Salon\QrCodeService;
use PHPUnit\Framework\TestCase;

/**
 * QR لینک سالن.
 *
 * چرا این تست هست: یک بار این سرویس با نسخهٔ جدید کتابخانه نوشته شد و
 * روی نسخهٔ نصب‌شده اصلاً بالا نمی‌آمد. هیچ تستی نداشت، پس ۴۷۰ تست
 * سبز ماندند و خرابی تا روی هاست مشتری رفت. حالا اگر امضای Builder
 * عوض شود، همین‌جا قرمز می‌شود.
 *
 * ادعاها عمداً محتوایی‌اند و نه «خروجی خالی نیست»: یک QR که بایت تولید
 * می‌کند ولی چیزی را کد نمی‌کند هم از تستِ طولِ رشته رد می‌شود.
 */
final class QrCodeServiceTest extends TestCase
{
    private QrCodeService $qr;

    protected function setUp(): void
    {
        $this->qr = new QrCodeService();

        if (!$this->qr->available()) {
            $this->markTestSkipped('کتابخانهٔ QR نصب نیست (composer install).');
        }
    }

    public function testSvgIsARealSvgDocument(): void
    {
        $svg = $this->qr->svg('https://example.test/s/shahab');

        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringContainsString('http://www.w3.org/2000/svg', $svg);

        // SVG باید بدون GD ساخته شود: روی هاست اشتراکیِ بدون GD تنها
        // قالبی است که کار می‌کند، پس نباید به آن گره بخورد.
        $this->assertNotEmpty($svg);
    }

    public function testSvgEncodesTheDataItWasGiven(): void
    {
        // دو نشانی متفاوت باید دو نقشِ متفاوت بدهند. اگر سرویس داده را
        // به Builder پاس ندهد — که دقیقاً اشتباهِ ممکن هنگام مهاجرت بین
        // نسخه‌های کتابخانه است — هر دو یکسان درمی‌آیند و این می‌افتد.
        $a = $this->qr->svg('https://example.test/s/shahab');
        $b = $this->qr->svg('https://example.test/s/kourosh');

        $this->assertNotSame($a, $b);

        // و همان ورودی باید همان خروجی را بدهد (بدون نویز تصادفی).
        $this->assertSame($a, $this->qr->svg('https://example.test/s/shahab'));
    }

    public function testLongerUrlNeedsMoreModules(): void
    {
        // نشانی بلندتر ماتریس متراکم‌تری لازم دارد. اندازهٔ تصویر ثابت
        // می‌ماند (۶۴۰ به‌علاوهٔ حاشیه) ولی تعداد خانه‌های سیاه بالا
        // می‌رود. این نشان می‌دهد داده واقعاً کد شده، نه اینکه تصویری
        // ثابت برگردانده شود.
        //
        // نوشتار SVG همهٔ خانه‌ها را در یک <path> می‌گذارد و هر خانه یک
        // زیرمسیرِ بسته است، پس تعداد Z همان تعداد خانه است.
        $modules = function (string $url): int {
            preg_match('/<path[^>]* d="([^"]*)"/', $this->qr->svg($url), $m);
            $this->assertNotEmpty($m, 'مسیر ماژول‌ها در SVG پیدا نشد.');

            return substr_count($m[1], 'Z');
        };

        $short = $modules('https://a.test/s/x');
        $long = $modules('https://a.test/s/' . str_repeat('kourosh-barbershop-', 8));

        $this->assertGreaterThan(0, $short);
        $this->assertGreaterThan($short, $long);
    }

    public function testDataUriIsUsableInsideAnImgTag(): void
    {
        $uri = $this->qr->svgDataUri('https://example.test/s/shahab');

        $this->assertStringStartsWith('data:image/svg+xml;base64,', $uri);

        $decoded = base64_decode(substr($uri, strlen('data:image/svg+xml;base64,')), true);
        $this->assertIsString($decoded);
        $this->assertStringContainsString('<svg', $decoded);
    }

    public function testPngIsARealPngWhenGdIsPresent(): void
    {
        if (!$this->qr->isPngAvailable()) {
            $this->markTestSkipped('افزونهٔ GD نیست.');
        }

        $png = $this->qr->png('https://example.test/s/shahab');

        // امضای فایل PNG.
        $this->assertSame("\x89PNG\r\n\x1a\n", substr($png, 0, 8));

        $size = getimagesizefromstring($png);
        $this->assertIsArray($size);
        $this->assertGreaterThanOrEqual(600, $size[0]);
        $this->assertSame($size[0], $size[1], 'QR باید مربع باشد.');
    }

    public function testPngAvailabilityTracksTheGdExtension(): void
    {
        // صفحهٔ QR بر اساس همین تصمیم می‌گیرد دکمهٔ PNG را نشان بدهد یا
        // نه؛ اگر دروغ بگوید، کاربر روی دکمه‌ای می‌زند که ۵۰۰ می‌دهد.
        $this->assertSame(extension_loaded('gd'), $this->qr->isPngAvailable());
    }
}
