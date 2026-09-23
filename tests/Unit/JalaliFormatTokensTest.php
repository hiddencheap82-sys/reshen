<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Jalali;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * هیچ ویویی نشانهٔ تاریخِ پشتیبانی‌نشده به کار نمی‌برد؟
 *
 * چرا این تست هست: Jalali::format با strtr کار می‌کند، و strtr هر
 * نشانه‌ای را که نشناسد دست‌نخورده رد می‌کند. یعنی `jdate($d, 'l j F Y')`
 * خطا نمی‌دهد — می‌نویسد «l ۱ F ۱۴۰۵». عادت‌شده‌ایم به date() فارسی
 * فکر نکنیم و `l` و `F` را خودکار تایپ کنیم، و نتیجه بی‌صدا روی صفحهٔ
 * مشتری می‌نشیند.
 *
 * (`l` و `F` را می‌شد پشتیبانی کرد، ولی آن‌وقت `D` و `M` دو راهِ
 * انجام یک کار می‌شدند. یک راه، و تستی که یادآوری‌اش کند.)
 */
final class JalaliFormatTokensTest extends TestCase
{
    /** نشانه‌هایی که Jalali::format می‌شناسد. */
    private const SUPPORTED = ['Y', 'm', 'n', 'M', 'd', 'j', 'D', 'H', 'i'];

    public function testEveryFormatTokenUsedInTheCodebaseIsSupported(): void
    {
        $offenders = [];

        foreach ([BASE_PATH . '/resources', BASE_PATH . '/app'] as $root) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));

            foreach ($files as $file) {
                if (!$file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }
                $body = (string) file_get_contents($file->getPathname());
                preg_match_all("/(?:jdate|Jalali::format)\s*\([^,)]+,\s*'([^']*)'/", $body, $m);

                foreach ($m[1] as $format) {
                    foreach (str_split($format) as $char) {
                        if (ctype_alpha($char) && !in_array($char, self::SUPPORTED, true)) {
                            $offenders[] = basename($file->getPathname()) . ": «{$format}» → «{$char}»";
                        }
                    }
                }
            }
        }

        $this->assertSame(
            [],
            array_values(array_unique($offenders)),
            'نشانهٔ تاریخِ پشتیبانی‌نشده. strtr آن را دست‌نخورده رد می‌کند و '
            . 'همان حرف لاتین روی صفحه می‌ماند. نشانه‌های مجاز: ' . implode(' ', self::SUPPORTED)
        );
    }

    /** نشانهٔ ناشناخته واقعاً بی‌صدا رد می‌شود — پس تست بالا لازم است. */
    public function testUnknownTokensPassThroughSilently(): void
    {
        $out = Jalali::format(new DateTimeImmutable('2026-09-23'), 'l j F Y');

        $this->assertStringContainsString('l', $out);
        $this->assertStringContainsString('F', $out);
    }

    public function testSupportedTokensAllProduceSomething(): void
    {
        $dt = new DateTimeImmutable('2026-09-23 14:05:00');

        foreach (self::SUPPORTED as $token) {
            $out = Jalali::format($dt, $token);
            $this->assertNotSame($token, $out, "نشانهٔ «{$token}» جایگزین نشد.");
            $this->assertNotSame('', $out);
        }
    }
}
