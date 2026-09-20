<?php

declare(strict_types=1);

use App\Core\Request;
use App\Core\Session;
use App\Support\Jalali;
use App\Support\Money;

if (!function_exists('e')) {
    function e(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        return Request::basePath() . '/' . ltrim($path, '/');
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        return url('assets/' . ltrim($path, '/'));
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return Session::csrfToken();
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
    }
}

if (!function_exists('flash')) {
    function flash(string $key): mixed
    {
        return Session::flash($key);
    }
}

if (!function_exists('jdate')) {
    function jdate(?string $datetime, string $format = 'Y/m/d H:i'): string
    {
        if ($datetime === null) {
            return '';
        }

        return Jalali::format(new DateTimeImmutable($datetime), $format);
    }
}

if (!function_exists('toman')) {
    function toman(int $rials): string
    {
        return Money::fromRials($rials)->formatToman();
    }
}

if (!function_exists('fa_num')) {
    function fa_num(int|string $value): string
    {
        return Jalali::toPersianDigits((string) $value);
    }
}

if (!function_exists('old')) {
    function old(string $key, mixed $default = ''): mixed
    {
        static $old = null;
        if ($old === null) {
            $old = Session::flash('_old') ?? [];
        }

        return $old[$key] ?? $default;
    }
}

if (!function_exists('icon')) {
    /**
     * آیکون از اسپرایت — «Lucide» با لایسنس ISC.
     *
     * چرا اسپرایت و نه SVG درون‌خطی در هر ویو: مسیرهای SVG تکراری،
     * هم HTML را باد می‌کنند هم نگهداری را سخت. با <use> هر آیکون یک
     * ارجاع است و مرورگر یک بار تعریفش را می‌خواند.
     *
     * چرا اموجی نه: اموجی روی هر سیستم‌عامل شکل دیگری دارد، با رنگ متن
     * هماهنگ نمی‌شود، و صفحه‌خوان اسمش را بلند می‌خواند.
     */
    function icon(string $name, string $class = 'w-5 h-5', ?string $label = null): string
    {
        $aria = $label === null
            ? 'aria-hidden="true"'
            : 'role="img" aria-label="' . e($label) . '"';

        return '<svg class="' . e($class) . '" ' . $aria . '>'
             . '<use href="#i-' . e($name) . '"></use></svg>';
    }
}

if (!function_exists('theme_attr')) {
    /** ویژگیِ data-theme برای تگ <html>. */
    function theme_attr(?string $key): string
    {
        return 'data-theme="' . e(App\Support\Theme::resolve($key)) . '"';
    }
}
