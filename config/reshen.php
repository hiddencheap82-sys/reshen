<?php

declare(strict_types=1);

/**
 * تنظیمات محصول رشن.
 *
 * هر عددی که در منطق صف، تخمین یا پیامک استفاده می‌شود اینجاست و نه در کد.
 * دلیل هر کدام در docs/10-architecture/04-queue-eta-engine.md آمده است.
 *
 * سالن‌ها می‌توانند بعضی از این‌ها را در salons.settings بازنویسی کنند؛
 * مقادیر اینجا پیش‌فرض کل پلتفرم‌اند.
 */
return [

    'queue' => [
        // نوبت رزروشده تا این تعداد دقیقه قبل و بعد از ساعتش،
        // بر مراجعه‌کنندگان حضوری مقدم است. بعد از آن به صف عادی برمی‌گردد.
        'priority_window_minutes' => env('RESHEN_PRIORITY_WINDOW', 10),

        // فاصلهٔ بین دو مشتری: جارو کردن، تمیز کردن، دست‌شویی.
        'turnover_buffer_minutes' => env('RESHEN_TURNOVER_BUFFER', 5),

        // فاصلهٔ بین دو خدمت در یک نوبت (عوض کردن ابزار).
        'multi_service_buffer_minutes' => 2,

        // هرگز نگو «همین الان نوبتته» — مشتری می‌دود می‌آید و باز منتظر می‌ماند.
        'min_remaining_minutes' => 2,

        // گذشتِ زمان خودش ETA را عوض می‌کند، حتی اگر هیچ رویدادی نیفتد.
        'recalculate_every_seconds' => 90,

        // بازهٔ بزرگ‌تر از این، به مشتری هیچ نمی‌گوید و فقط بی‌اعتمادی می‌سازد.
        'max_display_range_minutes' => 25,

        // بعد از این افق، تخمین آن‌قدر نویزی است که فقط scheduled_at نشان داده می‌شود.
        'estimate_horizon_minutes' => 180,

        // مشتری‌ای که نوبتش شد و نیامد، بعد از این مدت غیبت ثبت می‌شود.
        'no_show_after_minutes' => 15,
    ],

    'eta' => [
        // زیر این تعداد نمونه، میانه به یک روز عجیب حساس است — از مدت اسمی استفاده کن.
        'min_samples' => 8,

        // پنجرهٔ متحرک: آرایشگر با تجربه سریع‌تر می‌شود و آمار باید همراهش بیاید.
        'sample_window' => 200,

        // ضریب شخصی مشتری، بریده می‌شود تا یک دادهٔ پرت تخمین همه را خراب نکند.
        'customer_factor_min' => 0.7,
        'customer_factor_max' => 1.5,
        'customer_factor_min_visits' => 3,

        // خارج از این بازه، خطای ثبت است نه واقعیت. وارد آمار نمی‌شود.
        'outlier_min_minutes' => 5,
        'outlier_max_minutes' => 180,

        // آخرین پناه وقتی هیچ داده‌ای نیست.
        'fallback_duration_minutes' => 30,
    ],

    'sms' => [
        'max_per_appointment' => 4,

        // هیچ پیامکی در این بازه — مگر «صندلی آماده‌ست» که مشتری خودش در صف است.
        'quiet_hours' => ['23:00', '08:00'],

        // سالن وسط پنجشنبه شب نباید بی‌صدا شود. تا این حد موجودی منفی مجاز است.
        'emergency_credit' => -100,

        'low_balance_thresholds' => [30, 15, 5], // درصد

        // «نوبتت نزدیکه» وقتی ETA در این بازه باشد.
        'nearly_up_window_minutes' => [20, 30],

        // «عقب افتادیم» فقط وقتی تأخیر از این بیشتر شد — و فقط یک بار.
        'delay_notice_threshold_minutes' => 20,

        'daily_cap_per_salon' => 500,
    ],

    'booking' => [
        'slot_granularity_minutes' => 15,
        'max_days_ahead' => 30,
        'min_minutes_ahead' => 30,
        'max_per_phone_per_hour' => 5,
        'cancel_cutoff_minutes' => 60,
    ],

    'trust' => [
        'initial_score' => 80,
        'no_show_penalty' => 25,
        'late_cancel_penalty' => 10,
        'completed_reward' => 3,
        // زیر این امتیاز، رزرو آنلاین بیعانه می‌خواهد.
        'deposit_required_below' => 40,
        'decay_days' => 90,
    ],

    'otp' => [
        'length' => 4,
        'admin_length' => 5,
        'ttl_seconds' => 120,
        'resend_after_seconds' => 60,
        'max_attempts' => 5,
        'lockout_minutes' => 15,
    ],

    'tokens' => [
        'public_token_length' => 12,
        'public_token_ttl_days_after_end' => 7,
    ],

    // پنجشنبه ۱۶ تا ۲۲ و جمعه ۱۰ تا ۱۴ — شلوغ‌ترین ساعات هفته. استقرار ممنوع.
    'deploy_freeze' => [
        ['weekday' => 5, 'from' => '16:00', 'to' => '22:00'], // پنجشنبه
        ['weekday' => 6, 'from' => '10:00', 'to' => '14:00'], // جمعه
    ],
];
