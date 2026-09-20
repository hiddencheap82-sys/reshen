<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Clock;
use PHPUnit\Framework\TestCase;

/**
 * ساعت فارسی.
 *
 * چرا تست دارد: این کلاس جای <input type="time"> بومی را گرفت. اگر
 * fromParts() چیزی را بد بخواند، ساعت کاری سالن بی‌صدا عوض می‌شود —
 * و صاحب سالن تا وقتی مشتری سر ساعت غلط نیاید، نمی‌فهمد.
 */
final class ClockTest extends TestCase
{
    public function test_hm_is_24_hour_with_persian_digits(): void
    {
        self::assertSame('۰۹:۰۰', Clock::hm('09:00'));
        self::assertSame('۱۹:۳۰', Clock::hm('19:30'));
        self::assertSame('۰۰:۰۵', Clock::hm('00:05'));
        self::assertSame('۲۳:۵۹', Clock::hm('23:59'));
    }

    public function test_hm_accepts_seconds_from_database(): void
    {
        // ستون TIME در MySQL «۰۹:۰۰:۰۰» برمی‌گرداند
        self::assertSame('۰۹:۰۰', Clock::hm('09:00:00'));
    }

    public function test_period_uses_persian_words_not_am_pm(): void
    {
        self::assertSame('روز', Clock::period('00:00'));
        self::assertSame('روز', Clock::period('11:59'));
        self::assertSame('شب', Clock::period('12:00'));
        self::assertSame('شب', Clock::period('23:00'));
    }

    public function test_label_is_12_hour_with_persian_word(): void
    {
        self::assertSame('۹:۰۰ روز', Clock::label('09:00'));
        self::assertSame('۷:۳۰ شب', Clock::label('19:30'));
        // نیمه‌شب و ظهر هر دو «۱۲» می‌شوند، مثل عرف ۱۲ساعته
        self::assertSame('۱۲:۰۰ روز', Clock::label('00:00'));
        self::assertSame('۱۲:۰۰ شب', Clock::label('12:00'));
    }

    public function test_label_has_no_latin_characters(): void
    {
        foreach (['00:00', '06:15', '12:00', '18:45', '23:59'] as $t) {
            self::assertSame(
                0,
                preg_match('/[A-Za-z0-9]/', Clock::label($t)),
                "«{$t}» نباید هیچ حرف یا رقم لاتین داشته باشد"
            );
        }
    }

    public function test_part_of_day_puts_evening_in_night_not_afternoon(): void
    {
        self::assertSame('صبح', Clock::partOfDay('09:00'));
        self::assertSame('ظهر', Clock::partOfDay('13:00'));
        self::assertSame('عصر', Clock::partOfDay('16:30'));
        // رگرسیون: پیش‌تر هر چیزی بعد از ۱۶ «عصر» بود، حتی ۲۱:۰۰
        self::assertSame('شب', Clock::partOfDay('19:00'));
        self::assertSame('شب', Clock::partOfDay('21:00'));
        self::assertSame('شب', Clock::partOfDay('02:00'));
    }

    public function test_from_parts_builds_time(): void
    {
        self::assertSame('09:30', Clock::fromParts('9', '30'));
        self::assertSame('00:00', Clock::fromParts('0', '0'));
        self::assertSame('23:45', Clock::fromParts(23, 45));
    }

    /** خالی یعنی «تنظیم نشده» — نه نیمه‌شب. استراحت اختیاری به این تکیه دارد. */
    public function test_from_parts_returns_null_for_empty(): void
    {
        self::assertNull(Clock::fromParts('', '30'));
        self::assertNull(Clock::fromParts('9', ''));
        self::assertNull(Clock::fromParts(null, null));
    }

    public function test_from_parts_rejects_out_of_range(): void
    {
        self::assertNull(Clock::fromParts('24', '00'));
        self::assertNull(Clock::fromParts('-1', '00'));
        self::assertNull(Clock::fromParts('9', '60'));
    }

    public function test_minute_options_respect_step(): void
    {
        self::assertSame([0, 15, 30, 45], array_keys(Clock::minuteOptions(15)));
        self::assertSame(12, count(Clock::minuteOptions(5)));
    }

    public function test_hour_options_cover_full_day_in_persian(): void
    {
        $hours = Clock::hourOptions();
        self::assertCount(24, $hours);
        self::assertSame('۰۰', $hours[0]);
        self::assertSame('۲۳', $hours[23]);
    }
}
