<?php

declare(strict_types=1);

use App\Core\Request;
use App\Core\Session;
use App\Support\Jalali;
use App\Support\Money;

if (!defined('FILLED_ICONS')) {
    /**
     * آیکون‌هایی که نسخهٔ پرشده دارند.
     *
     * فهرست دستی است تا هر بار رندر، فایل سپرایت خوانده و جست‌وجو
     * نشود. `IconSpriteTest` نگه‌داشتنش را تضمین می‌کند: اگر نامی
     * اینجا باشد و در سپرایت `-fill` نداشته باشد، تست قرمز می‌شود.
     *
     * define و نه const: بقیهٔ فایل هم با نگهبان نوشته شده تا دو بار
     * include کردنش خطای مرگبار ندهد، و const داخل if مجاز نیست.
     */
    define('FILLED_ICONS', [
        'calendar', 'chart', 'clock', 'cog', 'home', 'message', 'more', 'qr',
        'queue', 'scissors', 'shield', 'tag', 'user', 'users', 'wallet',
    ]);
}

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

if (!function_exists('salon_logo_url')) {
    /** آدرس لوگوی سالن، یا null اگر نداشته باشد. */
    function salon_logo_url(?string $file): ?string
    {
        if ($file === null || $file === '') {
            return null;
        }

        $dir = (string) App\Core\Config::get('reshen.uploads.logos_dir', 'uploads/logos');

        // basename: نام از دیتابیس می‌آید ولی باز هم مسیرزدایی می‌شود.
        return url($dir . '/' . basename($file));
    }
}

if (!function_exists('absolute_url')) {
    /**
     * آدرس کامل با دامنه — برای QR، پیامک، و هر چیزی که بیرون از مرورگر
     * می‌رود و آدرس نسبی برایش بی‌معنی است.
     *
     * دامنه از خودِ درخواست خوانده می‌شود نه از APP_URL، چون صاحب سالن
     * ممکن است دامنه را عوض کند و یادش برود .env را به‌روز کند — آن‌وقت
     * QRای چاپ می‌شود که به جای اشتباه می‌برد. اگر درخواستی در کار نباشد
     * (اجرای کران از خط فرمان)، APP_URL می‌ماند.
     */
    function absolute_url(string $path = ''): string
    {
        $host = $_SERVER['HTTP_HOST'] ?? '';

        if ($host === '') {
            return rtrim((string) App\Core\Config::get('app.url', ''), '/')
                . '/' . ltrim($path, '/');
        }

        $https = ($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off';
        // پشت پراکسی یا کش (روی cPanel معمول است) طرح اصلی اینجا می‌آید.
        $proto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ($https ? 'https' : 'http');

        return $proto . '://' . $host . url($path);
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
        /*
         * ویژگیِ ‎hidden‎ روی ورودیِ پنهان زائد به نظر می‌رسد، ولی نیست:
         * ‎space-y-*‎ تیلویند فاصله را به هر فرزندی می‌دهد که ‎[hidden]‎
         * ندارد. این ورودی اولین فرزندِ تقریباً هر فرمی است، پس بخشِ
         * اولِ فرم یک فاصلهٔ اضافهٔ بی‌دلیل از بالا می‌گرفت.
         */
        return '<input type="hidden" hidden name="_csrf" value="' . e(csrf_token()) . '">';
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

if (!function_exists('jalali_date_from_request')) {
    /**
     * سه فیلدِ انتخابگر تاریخ شمسی را به «Y-m-d» میلادی تبدیل می‌کند.
     *
     * فهرست روز همیشه ۱ تا ۳۱ است چون طول ماه شمسی ثابت نیست (شش ماه
     * اول ۳۱، شش ماه بعد ۳۰، و اسفند ۲۹ یا ۳۰). اگر کسی «۳۱ مهر» را
     * انتخاب کند، به‌جای خطا دادن به آخرین روزِ همان ماه بریده می‌شود —
     * منظورِ کاربر روشن است و پرت کردنش از فرم بیرون، کمکی نمی‌کند.
     *
     * تاریخ ناقص یا بیرون از بازه، null برمی‌گرداند تا فراخوان تصمیم
     * بگیرد.
     */
    function jalali_date_from_request(App\Core\Request $request, string $name): ?string
    {
        $y = $request->input($name . '_y');
        $m = $request->input($name . '_m');
        $d = $request->input($name . '_d');

        if ($y === null || $y === '' || $m === null || $m === '' || $d === null || $d === '') {
            return null;
        }

        $y = (int) $y;
        $m = (int) $m;
        $d = (int) $d;

        if ($y < 1300 || $y > 1500 || $m < 1 || $m > 12 || $d < 1) {
            return null;
        }

        $d = min($d, Jalali::daysInJalaliMonth($y, $m));

        return Jalali::toDateTime($y, $m, $d)->format('Y-m-d');
    }
}

if (!function_exists('fa_time')) {
    /** «۱۹:۳۰» — ۲۴ساعته با رقم فارسی. برای ستون‌هایی که باید تراز بمانند. */
    function fa_time(?string $time): string
    {
        return $time === null || $time === '' ? '' : App\Support\Clock::hm($time);
    }
}

if (!function_exists('fa_time_label')) {
    /** «۷:۳۰ شب» — برای جایی که ساعت تنهاست و باید یک‌نگاهی خوانده شود. */
    function fa_time_label(?string $time): string
    {
        return $time === null || $time === '' ? '' : App\Support\Clock::label($time);
    }
}

if (!function_exists('toman')) {
    function toman(int $rials): string
    {
        return Money::fromRials($rials)->formatToman();
    }
}

if (!function_exists('phone_display')) {
    /**
     * شماره برای *نمایش*، به شکلی که ایرانی‌ها می‌خوانند: ‎۰۹۱۲ ۳۴۵ ۶۷۸۹‎.
     *
     * شماره‌ها به شکل بین‌المللی (‎+98912…‎) ذخیره می‌شوند که برای
     * پیامک و «تماس» درست است، ولی روی صفحه غریبه است — هیچ‌کس شمارهٔ
     * خودش را با ‎+98‎ نمی‌شناسد. چند صفحه IranMobile::local() را صدا
     * می‌زدند و بقیه شکلِ خام را چاپ می‌کردند؛ این تابع یک‌دستشان می‌کند.
     *
     * شمارهٔ سالن ممکن است ثابت باشد (‎021…‎) و IranMobile آن را
     * نمی‌پذیرد؛ آن‌وقت همان‌طور که هست، فقط با ارقام فارسی، برمی‌گردد.
     * پیوندِ tel: همچنان شکل بین‌المللی را می‌گیرد، نه این را.
     */
    function phone_display(?string $phone): string
    {
        $phone = trim((string) $phone);
        if ($phone === '') {
            return '';
        }

        $mobile = App\Support\IranMobile::tryParse($phone);

        return fa_num($mobile !== null ? $mobile->local() : $phone);
    }
}

if (!function_exists('fa_num')) {
    /**
     * عدد فارسی.
     *
     * اعشار هم قبول می‌کند، و این لازم است: دو سنجهٔ کلیدیِ پنل
     * پلتفرم — «خطای تخمین» و «نرخ ثبت پایان» — با
     * ‎round($x, 1)‎ حساب می‌شوند، یعنی float.
     *
     * پیش از این امضای تابع فقط ‎int|string‎ بود، پس PHP همان float
     * را بی‌سروصدا به int تبدیل می‌کرد: ۹۸٫۲ می‌شد ۹۸. یعنی دقتی که
     * کد عمداً حساب کرده بود، سرِ راهِ نمایش دور ریخته می‌شد — و روی
     * PHP 8.1 یک Deprecated هم می‌داد که در حالت دیباگ وسط صفحه
     * چاپ می‌شد.
     *
     * جداکنندهٔ اعشار «٫» است (U+066B)، نه نقطهٔ لاتین.
     */
    function fa_num(int|float|string $value): string
    {
        if (is_float($value)) {
            // صفرهای انتهایی حذف می‌شوند: ۹۸٫۲۰ → «۹۸٫۲»، ۹۸٫۰ → «۹۸»
            $value = rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
            $value = str_replace('.', '٫', $value);
        }

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

if (!function_exists('nav_icon')) {
    /**
     * آیکون یک تب ناوبری: خطی وقتی نیستی، پرشده وقتی هستی.
     *
     * قراردادی است که هر اپلیکیشن موبایلی دارد و کاربر بدون این‌که
     * فکر کند می‌خواندش. پیش از این تب فعال فقط رنگش عوض می‌شد، و
     * روی صفحهٔ کوچک — با نور آفتاب یا چشمِ خسته — تفاوت رنگ لهجهٔ
     * کم‌رنگ با خاکستری دیده نمی‌شد.
     *
     * اگر آیکون نسخهٔ پرشده نداشته باشد، همان خطی برمی‌گردد: نبودِ
     * یک نسخه نباید تب را ناپدید کند.
     */
    function nav_icon(string $name, bool $active, string $class = 'w-5 h-5'): string
    {
        return icon($active && in_array($name, FILLED_ICONS, true) ? $name . '-fill' : $name, $class);
    }
}

if (!function_exists('theme_attr')) {
    /** ویژگیِ data-theme برای تگ <html>. */
    function theme_attr(?string $key): string
    {
        return 'data-theme="' . e(App\Support\Theme::resolve($key)) . '"';
    }
}
