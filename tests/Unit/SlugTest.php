<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * ساخت نشانی عمومی سالن.
 *
 * این رشته جایی می‌رود که پس گرفتنش سخت است: روی QR چاپ‌شدهٔ پشت آینه،
 * در پیامک‌ها، و در لینکی که مشتری در واتساپ فوروارد می‌کند. پس هم باید
 * ASCII باشد (وگرنه درصدرمزگذاری‌شده حدود صد کاراکتر می‌شود و QR را
 * بی‌دلیل متراکم می‌کند) و هم پایدار — یک ورودی، همیشه یک خروجی.
 */
final class SlugTest extends TestCase
{
    /** @return array<string,array{string,string}> */
    public static function names(): array
    {
        return [
            'لاتین ساده' => ['Barber Shop', 'barber-shop'],
            'فارسی' => ['آرایشگاه شهاب', 'araishgah-shhab'],
            'و وسط کلمه مصوت است' => ['کوروش', 'kurush'],
            'و اول کلمه صامت است' => ['ولیعصر', 'vliasr'],
            'ی اول کلمه صامت است' => ['یاسر', 'yasr'],
            'ارقام فارسی' => ['سالن ۲۴', 'saln-24'],
            'نیم‌فاصله جداکننده می‌شود' => ['خوش‌تیپ', 'khush-tip'],
            'نشانه‌ها حذف می‌شوند' => ['!!! Test --- Shop !!!', 'test-shop'],
            'فاصلهٔ اضافه' => ['  دو   کلمه  ', 'du-klmh'],
        ];
    }

    #[DataProvider('names')]
    public function test_slug_is_generated(string $name, string $expected): void
    {
        self::assertSame($expected, Str::slug($name));
    }

    /** @return array<string,array{string}> */
    public static function anyName(): array
    {
        return [
            ['آرایشگاه شهاب'],
            ['سالن زیبایی رضا جون'],
            ['Café Niceté'],
            ['۱۲۳۴۵'],
            ['a'],
        ];
    }

    #[DataProvider('anyName')]
    public function test_output_is_always_url_safe_ascii(string $name): void
    {
        $slug = Str::slug($name);

        self::assertMatchesRegularExpression('/^[a-z0-9-]*$/', $slug);
        self::assertSame($slug, rawurlencode($slug), 'نباید نیازی به رمزگذاری داشته باشد.');
        self::assertStringNotContainsString('--', $slug);
        self::assertSame(trim($slug, '-'), $slug);
    }

    public function test_unusable_names_yield_an_empty_slug(): void
    {
        // تصمیم با صداکننده است — ثبت‌نام «salon» می‌گذارد، تنظیمات
        // خطا می‌دهد. خودِ تابع چیزی از خودش درنمی‌آورد.
        self::assertSame('', Str::slug('😀😀'));
        self::assertSame('', Str::slug('   '));
        self::assertSame('', Str::slug('---'));
    }

    public function test_the_same_name_always_gives_the_same_slug(): void
    {
        // اگر تصادفی بود، ذخیرهٔ دوبارهٔ فرم تنظیمات لینک را عوض می‌کرد
        // و QRهای چاپ‌شده می‌مردند.
        self::assertSame(Str::slug('آرایشگاه شهاب'), Str::slug('آرایشگاه شهاب'));
    }
}
