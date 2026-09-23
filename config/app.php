<?php

declare(strict_types=1);

use App\Core\Env;

return [
    'name' => Env::get('APP_NAME', 'reshen'),
    'env' => Env::get('APP_ENV', 'local'),
    'debug' => Env::bool('APP_DEBUG', true),
    'url' => Env::get('APP_URL', 'http://localhost'),
    'timezone' => Env::get('APP_TIMEZONE', 'Asia/Tehran'),
    'session_name' => Env::get('SESSION_NAME', 'reshen_session'),

    /**
     * کلید امضای برنامه. نصاب آن را می‌سازد.
     *
     * امروز هیچ کدی با آن امضا نمی‌کند: توکن‌های یک‌بارمصرف
     * (`LoginLinkService`) و CSRF هرکدام تصادفیِ خودشان را می‌سازند و
     * هش‌شده ذخیره می‌شوند، و مسیر کرون — تنها مصرف‌کنندهٔ واقعی‌اش —
     * با `Scheduler` حذف شد.
     *
     * پس چرا مانده: نصاب تنها جایی است که ساختن یک رازِ درست مجانی
     * است. صاحب سالن SSH ندارد و ویرایش `.env` از File Manager کار
     * سختی است؛ اگر روزی لینک امضاشده لازم شود، کلید از قبل آنجاست.
     */
    'key' => Env::get('APP_KEY', ''),
];
