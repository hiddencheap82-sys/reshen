<?php

declare(strict_types=1);

namespace App\Support;

/**
 * پالت رنگی سالن.
 *
 * هر سالن یک پالت انتخاب می‌کند و صفحهٔ اختصاصی‌اش همان رنگ را می‌گیرد.
 * این برای محصولی که قرار است «صفحهٔ اختصاصی هر آرایشگاه» بدهد مهم است:
 * سالن باید حس کند صفحه مالِ خودش است، نه یک قالب یکسان برای همه.
 *
 * پیاده‌سازی عمداً ساده است: نام پالت روی <html data-theme="..."> می‌نشیند
 * و CSS بقیه را انجام می‌دهد. نه کلاس اضافه‌ای در ویوها لازم است، نه
 * استایل درون‌خطی.
 *
 * همهٔ این رنگ‌ها با tools/assets/check-contrast.py سنجیده شده‌اند و در
 * هر دو حالت روشن و تیره از آستانهٔ WCAG AA (۴٫۵:۱) عبور می‌کنند.
 */
final class Theme
{
    public const DEFAULT = 'blue';

    /**
     * رنگ‌های سیستمی iOS.
     *
     * مقدارها از جدول «Increase Contrast» اپل گرفته شده‌اند، نه از
     * رنگ‌های پیش‌فرض: systemBlue روی متن سفید ۴٫۰۲ است و از WCAG AA
     * رد نمی‌شود. سبز کمی از نسخهٔ اپل هم تیره‌تر شد تا روی زمینهٔ
     * خاکستریِ صفحه (نه فقط روی سفید) از ۴٫۵ بگذرد.
     *
     * @var array<string,array{name:string,swatch:string}>
     */
    private const PALETTES = [
        'blue'   => ['name' => 'آبی',     'swatch' => '#0040DD'],
        'green'  => ['name' => 'سبز',     'swatch' => '#1C7A32'],
        'indigo' => ['name' => 'نیلی',    'swatch' => '#3634A3'],
        'orange' => ['name' => 'نارنجی',  'swatch' => '#C93400'],
        'pink'   => ['name' => 'صورتی',   'swatch' => '#D30F45'],
        'purple' => ['name' => 'بنفش',    'swatch' => '#8944AB'],
    ];

    /**
     * پالت‌های قبلی به نزدیک‌ترین رنگ سیستمی.
     *
     * سالن‌هایی که از قبل رنگ انتخاب کرده‌اند در دیتابیس کلید قدیمی
     * دارند. بدون این نگاشت، resolve آن‌ها را «ناشناخته» می‌دید و
     * همه‌شان یک‌شبه آبی می‌شدند — یعنی سالنی که زمردی انتخاب کرده
     * بود، بی‌آنکه کسی چیزی عوض کند، رنگش را از دست می‌داد.
     *
     * @var array<string,string>
     */
    private const LEGACY = [
        'gold'    => 'orange',
        'emerald' => 'green',
        'copper'  => 'orange',
        'teal'    => 'blue',
        'ruby'    => 'pink',
    ];

    /** @return array<string,array{name:string,swatch:string}> */
    public static function all(): array
    {
        return self::PALETTES;
    }

    public static function exists(string $key): bool
    {
        return isset(self::PALETTES[$key]);
    }

    /**
     * پالت معتبر، یا پیش‌فرض.
     *
     * هرگز مقدار خام را به ویو نده — اگر کسی در دیتابیس چیز دیگری
     * بنویسد، مستقیم در HTML می‌نشیند.
     */
    public static function resolve(?string $key): string
    {
        if ($key === null) {
            return self::DEFAULT;
        }
        if (self::exists($key)) {
            return $key;
        }

        return self::LEGACY[$key] ?? self::DEFAULT;
    }

    public static function name(string $key): string
    {
        return self::PALETTES[self::resolve($key)]['name'];
    }

    public static function swatch(string $key): string
    {
        return self::PALETTES[self::resolve($key)]['swatch'];
    }
}
