<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * هیچ ویویی رنگی به‌کار نمی‌برد که متنش خوانده نشود؟
 *
 * چرا این تست هست: کنتراستِ کم خطا نمی‌دهد. صفحه بالا می‌آید، متن
 * سر جایش است، و فقط *خوانده نمی‌شود* — آن هم نه برای همه، بلکه برای
 * کسی که زیر آفتاب ایستاده یا چشمش خسته است. یعنی دقیقاً همان
 * آرایشگری که وسط کار گوشی را برمی‌دارد.
 *
 * بازرسی مرورگر ۲۳ مورد در حالت روشن و ۳۱ مورد در حالت تیره پیدا کرد،
 * و همه از چند کلاسِ تکرارشونده می‌آمدند:
 *
 *   • ‎text-ink-300‎ رنگِ *جداکننده* است (‎#D6D3D1‎، نسبت ۱٫۴۹)، ولی
 *     در ۱۹ جا به‌عنوان متن استفاده شده بود.
 *   • ‎text-emerald-600‎ نسبتش ۳٫۷۷ و ‎text-red-500‎ نسبتش ۳٫۷۶ است —
 *     هر دو زیر ۴٫۵ برای متن کوچک.
 *   • ‎bg-amber-500‎ با متن سفید نسبتش ۲٫۱۵ بود؛ نوار «حالت پشتیبانی»
 *     که *باید* از همه بیشتر دیده شود.
 *
 * تست روی متنِ ویوهاست نه مرورگر، چون باید بدون سرور و دیتابیس هم
 * اجرا شود.
 */
final class ColorContrastTest extends TestCase
{
    /** سطحِ روشن و تیره‌ای که متن رویشان می‌نشیند. */
    private const LIGHT_SURFACE = '#FFFFFF';
    private const DARK_SURFACE = '#102431';

    /**
     * رنگ هر کلاسی که ویوها به‌عنوان *متن* به کار می‌برند.
     *
     * فقط آن‌هایی که واقعاً استفاده می‌شوند؛ فهرست کامل تیلویند اینجا
     * ارزشی ندارد.
     */
    private const TEXT_COLORS = [
        'text-ink-300' => '#D6D3D1',
        'text-ink-400' => '#6B6560',
        'text-ink-500' => '#57534E',
        'text-ink-600' => '#44403C',
        'text-ink-700' => '#3A3633',
        'text-ink-800' => '#292524',
        'text-ink-900' => '#1C1917',
        'text-emerald-500' => '#10B981',
        'text-emerald-600' => '#059669',
        'text-emerald-700' => '#047857',
        'text-green-600' => '#16A34A',
        'text-green-700' => '#15803D',
        'text-red-400' => '#F87171',
        'text-red-500' => '#EF4444',
        'text-red-600' => '#DC2626',
        'text-red-700' => '#B91C1C',
        'text-amber-500' => '#F59E0B',
        'text-amber-600' => '#D97706',
        'text-amber-700' => '#B45309',
    ];

    /**
     * رنگ‌هایی که متنِ سفید رویشان می‌نشیند.
     *
     * این‌ها بین حالت روشن و تیره عوض نمی‌شوند، پس یک بار سنجیدنشان
     * کافی است.
     */
    private const TINTS = [
        'bg-amber-500' => '#F59E0B',
        'bg-amber-600' => '#D97706',
        'bg-amber-700' => '#B45309',
        'bg-emerald-600' => '#059669',
        'bg-emerald-700' => '#047857',
        'bg-red-600' => '#DC2626',
        'bg-red-700' => '#B91C1C',
    ];

    private static function luminance(string $hex): float
    {
        $channels = array_map(
            static fn (string $pair): float => hexdec($pair) / 255,
            str_split(ltrim($hex, '#'), 2)
        );
        $linear = array_map(
            static fn (float $v): float => $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4,
            $channels
        );

        return 0.2126 * $linear[0] + 0.7152 * $linear[1] + 0.0722 * $linear[2];
    }

    private static function ratio(string $a, string $b): float
    {
        $la = self::luminance($a);
        $lb = self::luminance($b);

        return round((max($la, $lb) + 0.05) / (min($la, $lb) + 0.05), 2);
    }

    /** @return array<string,string> کلاس => فایلی که به کارش برده */
    private static function classesUsedInViews(array $wanted): array
    {
        $found = [];
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(BASE_PATH . '/resources/views')
        );

        foreach ($files as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $body = (string) file_get_contents($file->getPathname());

            foreach ($wanted as $class) {
                // مرزِ واژه لازم است: text-ink-40 نباید با text-ink-400 اشتباه شود.
                if (preg_match('~(?<![\w-])' . preg_quote($class, '~') . '(?![\w-])~', $body) === 1) {
                    $found[$class] ??= basename($file->getPathname());
                }
            }
        }

        return $found;
    }

    /** هیچ متنی روی سطح روشن زیر AA نباشد. */
    public function testEveryTextColourUsedInViewsPassesAaOnLight(): void
    {
        $used = self::classesUsedInViews(array_keys(self::TEXT_COLORS));
        $this->assertNotEmpty($used, 'هیچ کلاس رنگی پیدا نشد — الگوی جست‌وجو خراب است.');

        $bad = [];
        foreach ($used as $class => $file) {
            $ratio = self::ratio(self::TEXT_COLORS[$class], self::LIGHT_SURFACE);
            if ($ratio < 4.5) {
                $bad[] = "{$class} = {$ratio} (در {$file})";
            }
        }

        $this->assertSame([], $bad, "رنگ متنی که روی سطح روشن از AA رد نمی‌شود.\n"
            . 'برای «خوب» و «بد» از ‎text-ok‎ و ‎text-bad‎ استفاده کنید — با تم برمی‌گردند.');
    }

    /** و متنِ سفید روی هیچ رنگی زیر AA نباشد. */
    public function testWhiteTextOnTintsPassesAa(): void
    {
        $used = self::classesUsedInViews(array_keys(self::TINTS));

        $bad = [];
        foreach ($used as $class => $file) {
            $ratio = self::ratio(self::TINTS[$class], '#FFFFFF');
            if ($ratio < 4.5) {
                $bad[] = "{$class} = {$ratio} (در {$file})";
            }
        }

        $this->assertSame([], $bad, 'متن سفید روی این رنگ‌ها خوانده نمی‌شود.');
    }

    /**
     * ‎ink-300‎ رنگِ جداکننده است و هیچ‌وقت متن نیست.
     *
     * جدا از تست بالا نوشته شده چون دلیلش فرق می‌کند: این یکی فقط
     * «کم‌کنتراست» نیست، بلکه استفاده از یک توکن در جای اشتباه است.
     */
    public function testInk300IsNeverUsedForText(): void
    {
        $used = self::classesUsedInViews(['text-ink-300']);

        $this->assertSame(
            [],
            $used,
            'ink-300 رنگِ خط و جداکننده است (نسبت ۱٫۴۹ روی سفید)، نه متن. '
            . 'برای متن کم‌رنگ از ink-400 استفاده کنید.'
        );
    }

    /**
     * متنِ سفیدِ روی رنگِ اشباع باید ‎on-tint‎ داشته باشد.
     *
     * در حالت تیره ‎text-white‎ عمداً به ‎#E9F1F6‎ وارونه می‌شود، چون
     * ویوها روی سطحی که خودش وارونه می‌شود از آن استفاده می‌کنند. ولی
     * رنگِ آرایشگر و آواتار وارونه نمی‌شوند، پس متنشان هم نباید.
     */
    public function testWhiteTextOnAnInlineBackgroundOptsOutOfInversion(): void
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

            // تگ‌هایی که هم text-white دارند و هم style با background
            preg_match_all('~<[a-z]+\b[^>]*>~is', $body, $tags);

            foreach ($tags[0] as $tag) {
                if (!str_contains($tag, 'text-white')) {
                    continue;
                }
                if (!preg_match('~style="[^"]*background~i', $tag)) {
                    continue;
                }
                if (str_contains($tag, 'on-tint') || str_contains($tag, 'on-dark')) {
                    continue;
                }
                $offenders[] = basename($file->getPathname());
            }
        }

        $this->assertSame(
            [],
            array_values(array_unique($offenders)),
            'متن سفید روی پس‌زمینهٔ درون‌خطی، بدون ‎on-tint‎. در حالت تیره وارونه می‌شود '
            . 'و کنتراست می‌افتد.'
        );
    }
}
