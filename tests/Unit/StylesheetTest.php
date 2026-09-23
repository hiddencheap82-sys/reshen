<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * هر کلاسی که ویوها به کار می‌برند، در CSS ساخته‌شده هست؟
 *
 * چرا این تست هست: تیلویند فقط کلاس‌هایی را می‌سازد که در ویوها
 * *دیده* باشد، و خروجی‌اش در ریپو کامیت می‌شود (تصمیم ت-۰۳) تا هاست
 * cPanel مرحلهٔ ساخت نداشته باشد. یعنی ویوی تازه بدون اجرای
 * `tools/assets/build.sh` کلاس‌هایش هیچ اثری ندارد — و خطا هم نمی‌دهد.
 *
 * یک بار دقیقاً همین شد: نوار نسبتِ آرایشگرها با `h-1.5` کشیده شده
 * بود و چون CSS پیش از نوشتن آن ویو ساخته شده بود، ارتفاعش صفر ماند.
 * صفحه سالم بالا می‌آمد، فقط نوار نامرئی بود.
 *
 * ایراد دوم که می‌گیرد: کلاسی که اصلاً وجود ندارد. `border-brand-300`
 * سال‌ها در صفحهٔ انتخاب سالن بود در حالی که پلهٔ ۳۰۰ در پالت brand
 * تعریف نشده — یک hover که هیچ‌وقت کار نکرد.
 */
final class StylesheetTest extends TestCase
{
    /**
     * کلاس‌هایی که عمداً در CSS نیستند.
     *
     * `group` و `peer` نشانه‌گذاری تیلویندند و خودشان قاعده‌ای
     * نمی‌سازند؛ بقیه قلابِ جاوااسکریپت‌اند، نه کلاس ظاهری.
     */
    private const NOT_STYLE_CLASSES = ['group', 'peer', 'copy-btn', 'mode-moon', 'mode-sun'];

    public function testEveryClassUsedInViewsExistsInTheBuiltStylesheet(): void
    {
        $css = (string) file_get_contents(BASE_PATH . '/public/assets/css/app.css');
        $this->assertNotSame('', $css, 'CSS ساخته‌شده خالی است.');

        $missing = [];
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(BASE_PATH . '/resources/views')
        );

        foreach ($files as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $body = (string) file_get_contents($file->getPathname());
            preg_match_all('/class="([^"]*)"/', $body, $m);

            foreach ($m[1] as $attribute) {
                // کلاسِ ساخته‌شده با PHP را نمی‌شود ثابت خواند.
                if (str_contains($attribute, '<?')) {
                    continue;
                }

                foreach (preg_split('/\s+/', trim($attribute)) ?: [] as $class) {
                    if ($class === '' || in_array($class, self::NOT_STYLE_CLASSES, true)) {
                        continue;
                    }
                    // فقط چیزی که شکل کلاس تیلویند دارد.
                    if (preg_match('~^[a-z0-9:\-\[\]./%()#!_]+$~', $class) !== 1) {
                        continue;
                    }
                    if (!str_contains($css, '.' . preg_replace('~([.:/\[\]()%!#,])~', '\\\\$1', $class))) {
                        $missing[$class] = basename($file->getPathname());
                    }
                }
            }
        }

        $report = [];
        foreach ($missing as $class => $file) {
            $report[] = "{$class} (در {$file})";
        }

        $this->assertSame(
            [],
            $report,
            "کلاس‌هایی که در CSS ساخته‌شده نیستند.\n"
            . "اگر کلاس تازه‌ای اضافه کرده‌اید: tools/assets/build.sh را اجرا کنید.\n"
            . 'اگر کلاس اصلاً وجود ندارد (مثل یک پلهٔ رنگیِ تعریف‌نشده): درستش کنید — بی‌اثر است.'
        );
    }
}
