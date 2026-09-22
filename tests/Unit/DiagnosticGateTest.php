<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * دروازه‌های نصاب و صفحهٔ سلامت.
 *
 * این دو صفحه تنها چیزی هستند که وقتی برنامه بالا نمی‌آید کاربر دارد.
 * اگر خودشان به autoload یا bootstrap دست بزنند، با همان خطایی
 * می‌میرند که قرار بود توضیحش بدهند — و کاربرِ بدون SSH فقط
 * «HTTP ERROR 500» می‌بیند، بدون هیچ سرنخی.
 *
 * دقیقاً دو بار همین افتاد: یک بار نصاب (به‌خاطر سینتکس PHP 8.2 در
 * فایلی که ادعا می‌کرد روی ۸.۱ کار می‌کند) و یک بار صفحهٔ سلامت
 * (به‌خاطر `platform_check.php` که وابستگی‌ها ساخته بودند). هر دو بار
 * ابزار تشخیص، به بیماریِ خودش مُرد.
 *
 * پس این تست ساختار را قفل می‌کند، نه رفتار را: دروازه حق ندارد چیزی
 * جز فایلِ جفتش را بارگذاری کند.
 */
final class DiagnosticGateTest extends TestCase
{
    /** @return array<string, array{string, string}> */
    public static function gates(): array
    {
        return [
            'نصاب' => ['public/install.php', 'app/Setup/installer.php'],
            'سلامت' => ['public/doctor.php', 'app/Setup/doctor.php'],
        ];
    }

    private function source(string $file): string
    {
        $path = dirname(__DIR__, 2) . '/' . $file;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    #[DataProvider('gates')]
    public function testGateLoadsNothingButItsOwnApp(string $gate, string $app): void
    {
        $loads = $this->loadedPaths($this->source($gate));

        $this->assertNotEmpty($loads, "«{$gate}» باید فایل اصلی را صدا بزند.");

        foreach ($loads as $line => $target) {
            $this->assertStringContainsString(
                basename($app),
                $target,
                "«{$gate}» خط {$line} چیزی جز «{$app}» را بارگذاری می‌کند. "
                . 'دروازه پیش از سنجیدن محیط نباید به autoload یا bootstrap دست بزند.'
            );
        }
    }

    #[DataProvider('gates')]
    public function testAppRefusesToRunWithoutItsGate(string $gate, string $app): void
    {
        $src = $this->source($app);

        // ثابتِ نگهبان باید پیش از هر بارگذاری‌ای بررسی شود، وگرنه باز
        // کردن مستقیم فایل، دروازه را دور می‌زند.
        $guard = strpos($src, "!defined(");
        $this->assertNotFalse($guard, "«{$app}» نگهبان defined() ندارد.");

        $loads = $this->loadedPaths($src);
        $this->assertNotEmpty($loads, "«{$app}» چیزی بارگذاری نمی‌کند؟");

        foreach ($loads as $line => $target) {
            $this->assertGreaterThan(
                $guard,
                strpos($src, $target),
                "«{$app}» خط {$line} را پیش از نگهبان بارگذاری می‌کند."
            );
        }
    }

    #[DataProvider('gates')]
    public function testOnlyTheGateSitsInTheWebRoot(string $gate, string $app): void
    {
        // دروازه کوچک است و باید از وب قابل باز شدن باشد. پیاده‌سازی —
        // ۶۰۰ خط که دیتابیس می‌سازد و .env می‌نویسد — کاری در ریشهٔ وب
        // ندارد. در app/ می‌نشیند، که .htaccess ریشه از وب می‌بنددش.
        $this->assertStringStartsWith('public/', $gate);
        $this->assertStringStartsWith('app/', $app);

        $htaccess = (string) file_get_contents(dirname(__DIR__, 2) . '/.htaccess');
        $this->assertStringContainsString(
            'app',
            $htaccess,
            '.htaccess باید پوشهٔ app را از وب ببندد.'
        );
    }

    #[DataProvider('gates')]
    public function testGateDeclaresTheMinimumItChecks(string $gate): void
    {
        $src = $this->source($gate);

        $this->assertMatchesRegularExpression(
            "/define\('RESHEN_MIN_PHP', '(\d+\.\d+\.\d+)'\)/",
            $src,
            "«{$gate}» باید کمترین نسخهٔ PHP را صریح اعلام کند."
        );

        preg_match("/define\('RESHEN_MIN_PHP', '(\d+)\.(\d+)/", $src, $m);
        $declared = ((int) $m[1]) * 10000 + ((int) $m[2]) * 100;

        // همان چیزی که composer.json ادعا می‌کند. اگر این دو از هم جدا
        // بیفتند، یکی از دو طرف به کاربر دروغ می‌گوید.
        $composer = json_decode(
            (string) file_get_contents(dirname(__DIR__, 2) . '/composer.json'),
            true
        );
        preg_match('/(\d+)\.(\d+)/', (string) ($composer['require']['php'] ?? ''), $c);
        $required = ((int) $c[1]) * 10000 + ((int) $c[2]) * 100;

        $this->assertSame(
            $required,
            $declared,
            "«{$gate}» نسخهٔ دیگری غیر از composer.json می‌گوید."
        );
    }

    /**
     * خطوطی که فایلی را بارگذاری می‌کنند، با مقصدشان.
     *
     * با توکن‌های خود PHP خوانده می‌شود و نه با regex: کلمهٔ `require`
     * داخل یک کامنت فارسی نباید به حساب بیاید — و این فایل‌ها پر از
     * کامنت فارسی‌اند که دقیقاً دربارهٔ همین موضوع توضیح می‌دهند.
     *
     * @return array<int, string>
     */
    private function loadedPaths(string $src): array
    {
        $tokens = token_get_all($src);
        $out = [];

        foreach ($tokens as $i => $token) {
            if (!is_array($token)) {
                continue;
            }
            if (!in_array($token[0], [T_REQUIRE, T_REQUIRE_ONCE, T_INCLUDE, T_INCLUDE_ONCE], true)) {
                continue;
            }

            $target = '';
            for ($j = $i + 1; $j < count($tokens); $j++) {
                $next = $tokens[$j];
                if ($next === ';') {
                    break;
                }
                $target .= is_array($next) ? $next[1] : $next;
            }
            $out[$token[2]] = trim($target);
        }

        return $out;
    }
}
