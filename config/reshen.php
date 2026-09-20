<?php

declare(strict_types=1);

use App\Core\Env;

/**
 * All business tunables for the queue/ETA engine and messaging rules live
 * here per the product spec (section 8.6 / 11.3) — never as magic numbers
 * scattered through the domain code.
 */
return [

    'ui' => [
        /*
         * خانوادهٔ فونت. «vazirmatn» یا «iranyekan».
         *
         * ایران‌یکان فونت تجاری است و در مخزن نیست. اگر لایسنسش را
         * دارید، فایل‌های woff2 را در public/assets/fonts بگذارید و
         * این را عوض کنید. راهنما: public/assets/fonts/README.md
         */
        'font' => Env::get('UI_FONT', 'vazirmatn'),
    ],
    'queue' => [
        // Booked appointments outrank walk-ins from N minutes before to N after their slot.
        'priority_window_minutes' => 10,
        // Cleanup / cigarette buffer between customers.
        'buffer_minutes' => 5,
        // Never estimate "right now" — floor on remaining time for the person in the chair.
        'min_remaining_minutes' => 2,
        // Never promise more than 3 hours out — error is meaningless beyond that.
        'max_horizon_minutes' => 180,
    ],

    'estimation' => [
        // Minimum real samples of (staff, service) before trusting the learned percentile.
        'min_samples_for_learning' => 8,
        // Rolling window of most recent samples used to compute percentiles.
        'rolling_window_samples' => 200,
        // Outlier filtering: durations outside this range are discarded from stats.
        'outlier_min_minutes' => 5,
        'outlier_max_minutes' => 180,
        // Customer personal duration factor: needs at least this many visits to activate.
        'customer_factor_min_visits' => 3,
        'customer_factor_min' => 0.7,
        'customer_factor_max' => 1.5,
        // Percentiles used for the promised window (p50 = lower bound, p80 = upper bound).
        'lower_percentile' => 50,
        'upper_percentile' => 80,
        // Fallback nominal duration in minutes when nothing else is known.
        'fallback_minutes' => 30,
    ],

    'display' => [
        // Range wider than this is capped and flagged as "rough estimate".
        'max_window_minutes' => 25,
        'imminent_threshold_minutes' => 15,
        'far_threshold_minutes' => 60,
    ],

    'sms' => [
        'driver' => Env::get('SMS_DRIVER', 'log'),

        /**
         * Approved-template ids, per provider. Iranian carriers will not
         * deliver a free-text OTP over a shared service line, so without a
         * registered template the login code silently never arrives — which
         * is why doc 8.7 says to start the approval paperwork in week zero,
         * not the last week. Leave empty in dev: the `log` driver ignores it.
         */
        'patterns' => [
            'melipayamak' => [
                'otp' => Env::get('SMS_PATTERN_MELIPAYAMAK_OTP', ''),
            ],
            'kavenegar' => [
                'otp' => Env::get('SMS_PATTERN_KAVENEGAR_OTP', ''),
            ],
        ],

        // OTP abuse limits. A per-phone cooldown alone is not enough: one
        // attacker cycling many numbers from a single IP never trips it.
        'otp_hourly_limit_phone' => 5,
        'otp_hourly_limit_ip' => 15,

        'max_per_appointment' => 4,
        'quiet_hours_start' => 23,
        'quiet_hours_end' => 8,
        'nearly_up_window_min' => 20,
        'nearly_up_window_max' => 30,
        'delay_threshold_minutes' => 20,
        'reminder_hours_before' => [24, 2],
        'low_balance_thresholds' => [30, 15, 5],
        'emergency_credit' => 100,
    ],

    'payments' => [
        'driver' => Env::get('PAYMENT_DRIVER', 'manual'),
    ],
];
