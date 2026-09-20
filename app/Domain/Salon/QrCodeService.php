<?php

declare(strict_types=1);

namespace App\Domain\Salon;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;

/**
 * QR لینک عمومی سالن.
 *
 * برای چه: آرایشگاه یک برگه پشت آینه یا روی پیشخوان می‌چسباند و مشتری
 * با دوربین موبایل مستقیم می‌رود سر صفحهٔ رزرو. تایپ کردن نشانی روی
 * موبایل، جایی که اکثر مشتری‌ها وسط کار می‌ایستند.
 *
 * دو قالب می‌دهد و دلیلش عملی است:
 *   SVG  برای چاپ. هر اندازه‌ای بزرگ شود لبه‌ها تیز می‌ماند، و
 *        ساختنش به افزونهٔ GD نیاز ندارد — روی هاست اشتراکی که GD
 *        نصب نیست هم کار می‌کند.
 *   PNG  برای فرستادن در واتساپ و اینستاگرام، که SVG را نشان نمی‌دهند.
 *
 * سطح تصحیح خطا «بالا» است: برگه‌ای که کنار سشوار و روی پیشخوان
 * می‌ماند لک و خط می‌افتد، و QR باید باز هم خوانده شود.
 */
final class QrCodeService
{
    private const SIZE = 640;

    public function svg(string $url): string
    {
        return $this->build($url, new SvgWriter())->getString();
    }

    public function png(string $url): string
    {
        return $this->build($url, new PngWriter())->getString();
    }

    /** برای نشان دادن داخل صفحه بدون درخواست دوم به سرور. */
    public function svgDataUri(string $url): string
    {
        return 'data:image/svg+xml;base64,' . base64_encode($this->svg($url));
    }

    /**
     * آیا کتابخانهٔ QR در دسترس است؟
     *
     * اگر کسی ریپو را clone کرده و composer install نزده، vendor نیست.
     * صفحه باید به‌جای خطای ۵۰۰، لینک را نشان بدهد و بگوید QR نساختنی
     * نیست.
     */
    public function available(): bool
    {
        return class_exists(\Endroid\QrCode\Builder\Builder::class);
    }

    public function isPngAvailable(): bool
    {
        return extension_loaded('gd');
    }

    private function build(string $url, PngWriter|SvgWriter $writer): \Endroid\QrCode\Writer\Result\ResultInterface
    {
        return new Builder(
            writer: $writer,
            data: $url,
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: self::SIZE,
            margin: 16,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
        )->build();
    }
}
