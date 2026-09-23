<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * سپرایت آیکون‌ها سالم است؟
 *
 * چرا این تست هست: آیکونی که وجود ندارد، خطا نمی‌دهد. مرورگر
 * <use href="#i-چیزی-که-نیست"> را بی‌صدا نادیده می‌گیرد و فقط یک
 * سوراخ خالی می‌ماند. چهار ویو ماه‌ها `calendar-days` صدا می‌زدند و
 * هیچ‌کدام از ۵۶۸ تستِ سبز خبر نداشتند — حتی صفحهٔ «نوبتی نداری» که
 * وسطش یک آیکون ۳۶ پیکسلیِ نامرئی داشت.
 *
 * ایراد دوم که اینجا قفل می‌شود: هر symbol روزی با پوششِ <svg> خودش
 * کپی شده بود و آن <svg> بسته هم نمی‌شد. مرورگر ترمیمش می‌کرد، ولی
 * <svg> تودرتو یک viewport تازه می‌سازد و اندازه‌دهی را می‌شکند.
 */
final class IconSpriteTest extends TestCase
{
    private static function sprite(): string
    {
        return (string) file_get_contents(BASE_PATH . '/resources/views/components/icons.svg');
    }

    /**
     * سپرایت بدون کامنت.
     *
     * لازم است چون کامنتِ بالای فایل خودش واژه‌های <symbol> و <svg> را
     * توضیح می‌دهد؛ الگوی جست‌وجو آن متن را هم می‌گیرد و تست به خودِ
     * توضیحِ باگ گیر می‌دهد.
     */
    private static function markup(): string
    {
        return (string) preg_replace('/<!--.*?-->/s', '', self::sprite());
    }

    /** @return list<string> */
    private static function symbols(): array
    {
        preg_match_all('/<symbol[^>]*id="i-([^"]+)"/', self::markup(), $m);

        return $m[1];
    }

    /**
     * نام آیکون‌هایی که ویوها صدا می‌زنند.
     *
     * فقط رشتهٔ ثابت را می‌بیند. نام‌هایی که از آرایه می‌آیند (نوار
     * ناوبری) اینجا پیدا نمی‌شوند — برای همین `#i-` را هم می‌گیریم و
     * آرایه‌های ناوبری تستِ جداگانه دارند.
     *
     * @return array<string,string> نام آیکون => فایلی که صدایش زده
     */
    private static function referenced(): array
    {
        $out = [];
        $dir = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(BASE_PATH . '/resources/views')
        );

        foreach ($dir as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $body = (string) file_get_contents($file->getPathname());
            if (preg_match_all("/icon\(\s*'([a-z0-9-]+)'/", $body, $m) > 0) {
                foreach ($m[1] as $name) {
                    $out[$name] ??= $file->getPathname();
                }
            }
        }

        return $out;
    }

    public function testEveryIconCalledByAViewExists(): void
    {
        $have = self::symbols();

        foreach (self::referenced() as $name => $file) {
            $this->assertContains(
                $name,
                $have,
                "ویو " . basename($file) . " آیکون «{$name}» را صدا می‌زند ولی در سپرایت نیست — "
                . 'مرورگر جایش را خالی می‌گذارد و هیچ خطایی نمی‌دهد.'
            );
        }
    }

    /**
     * نام آیکون‌های نوار ناوبری از آرایه می‌آید، نه رشتهٔ ثابت.
     *
     * این دو فایل تنها جایی‌اند که نامِ آیکون متغیر است، پس دستی
     * بررسی می‌شوند.
     */
    public function testNavigationIconNamesExist(): void
    {
        $have = self::symbols();
        $files = [
            '/resources/views/layouts/panel.php',
            '/resources/views/platform/_nav.php',
        ];

        $found = 0;
        foreach ($files as $path) {
            $body = (string) file_get_contents(BASE_PATH . $path);
            preg_match_all("/'icon'\s*=>\s*'([a-z0-9-]+)'|,\s*'([a-z0-9-]+)'\]/", $body, $m);

            foreach (array_merge($m[1], $m[2]) as $name) {
                if ($name === '') {
                    continue;
                }
                ++$found;
                $this->assertContains($name, $have, "آیکون ناوبری «{$name}» در سپرایت نیست.");
            }
        }

        $this->assertGreaterThan(10, $found, 'الگوی یافتن نام آیکون‌های ناوبری دیگر کار نمی‌کند.');
    }

    /** هیچ symbol نباید <svg> تودرتو داشته باشد. */
    public function testNoNestedSvgInsideSymbols(): void
    {
        preg_match_all('/<symbol\b.*?<\/symbol>/s', self::markup(), $m);
        $this->assertNotEmpty($m[0], 'هیچ symbol پیدا نشد — سپرایت خراب است.');

        foreach ($m[0] as $symbol) {
            preg_match('/id="(i-[^"]+)"/', $symbol, $id);
            $this->assertStringNotContainsString(
                '<svg',
                $symbol,
                "symbol «{$id[1]}» یک <svg> تودرتو دارد؛ viewport تازه می‌سازد و اندازه را می‌شکند."
            );
        }
    }

    /**
     * فهرست FILLED_ICONS در helpers.php با سپرایت جور است؟
     *
     * اگر نامی آنجا باشد و اینجا نسخهٔ پرشده نداشته باشد، تب فعال یک
     * سوراخ خالی می‌شود — دقیقاً همان خرابیِ بی‌صدایی که این فایل
     * برای گرفتنش نوشته شده.
     */
    public function testEveryFilledIconInTheHelperListExists(): void
    {
        require_once BASE_PATH . '/app/Support/helpers.php';
        $have = self::symbols();

        $this->assertNotEmpty(FILLED_ICONS);

        foreach (FILLED_ICONS as $name) {
            $this->assertContains($name, $have, "آیکون خطیِ «{$name}» در سپرایت نیست.");
            $this->assertContains(
                $name . '-fill',
                $have,
                "«{$name}» در FILLED_ICONS هست ولی «{$name}-fill» در سپرایت نیست؛ "
                . 'تب فعال خالی رندر می‌شود.'
            );
        }
    }

    /** هر آیکون پرشده‌ای که ساخته‌ایم، در فهرست helpers.php هم هست؟ */
    public function testNoFilledIconIsLeftUnusedByTheHelper(): void
    {
        require_once BASE_PATH . '/app/Support/helpers.php';

        foreach (self::symbols() as $name) {
            if (!str_ends_with($name, '-fill')) {
                continue;
            }
            $base = substr($name, 0, -5);
            $this->assertContains(
                $base,
                FILLED_ICONS,
                "«{$name}» ساخته شده ولی «{$base}» در FILLED_ICONS نیست، پس هیچ‌وقت استفاده نمی‌شود."
            );
        }
    }

    public function testSpriteIsWellFormedXml(): void
    {
        $body = self::markup();

        $previous = libxml_use_internal_errors(true);
        $ok = simplexml_load_string((string) $body) !== false;
        $errors = array_map(static fn ($e) => trim($e->message), libxml_get_errors());
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $this->assertTrue($ok, 'سپرایت XML معتبر نیست: ' . implode(' · ', $errors));
    }

    /** هر آیکون خطی باید viewBox و stroke داشته باشد، هر پرشده fill. */
    public function testSymbolsDeclareTheirOwnGeometry(): void
    {
        preg_match_all('/<symbol([^>]*)id="i-([^"]+)"([^>]*)>/', self::markup(), $m, PREG_SET_ORDER);

        foreach ($m as [$whole, , $name]) {
            $this->assertStringContainsString(
                'viewBox="0 0 24 24"',
                $whole,
                "symbol «{$name}» بدون viewBox است؛ با کلاس‌های w-/h- درست مقیاس نمی‌شود."
            );

            if (str_ends_with($name, '-fill')) {
                $this->assertStringContainsString('fill="currentColor"', $whole, "«{$name}» باید پر شود.");
            } else {
                $this->assertStringContainsString('stroke="currentColor"', $whole, "«{$name}» باید خطی باشد.");
            }
        }
    }
}
