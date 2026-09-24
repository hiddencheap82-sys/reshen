<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\JalaliCalendar;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * شبکهٔ تقویم شمسی.
 *
 * چیزی که این تست‌ها محافظت می‌کنند: اگر ستون اول هفته اشتباه باشد،
 * کل تقویم یک خانه جابه‌جا می‌شود و مشتری روی «شنبه» کلیک می‌کند ولی
 * جمعه نوبت می‌گیرد — بدون اینکه چیزی خطا بدهد.
 */
final class JalaliCalendarTest extends TestCase
{
    public function test_week_columns_start_on_saturday(): void
    {
        self::assertSame('ش', JalaliCalendar::WEEKDAY_INITIALS[0]);
        self::assertSame('ج', JalaliCalendar::WEEKDAY_INITIALS[6]);
        self::assertSame('شنبه', JalaliCalendar::WEEKDAY_NAMES[0]);
    }

    public function test_first_day_lands_in_correct_column(): void
    {
        // ۱ فروردین ۱۴۰۵ = ۲۱ مارس ۲۰۲۶ = شنبه → ستون ۰
        $grid = JalaliCalendar::month(1405, 1, [], new DateTimeImmutable('2026-03-21'));

        self::assertNotNull($grid['weeks'][0][0], 'اول فروردین ۱۴۰۵ باید در ستون شنبه باشد');
        self::assertSame(1, $grid['weeks'][0][0]['jday']);
        self::assertSame('2026-03-21', $grid['weeks'][0][0]['gregorian']);
    }

    public function test_leading_cells_are_null_when_month_starts_midweek(): void
    {
        // ۱ مهر ۱۴۰۵ = ۲۳ سپتامبر ۲۰۲۶ = چهارشنبه → ستون ۴
        $grid = JalaliCalendar::month(1405, 7, [], new DateTimeImmutable('2026-09-23'));

        self::assertNull($grid['weeks'][0][0], 'شنبه باید خالی باشد');
        self::assertNull($grid['weeks'][0][3], 'سه‌شنبه باید خالی باشد');
        self::assertNotNull($grid['weeks'][0][4], 'اول ماه باید در ستون چهارشنبه باشد');
        self::assertSame(1, $grid['weeks'][0][4]['jday']);
    }

    public function test_every_week_has_exactly_seven_cells(): void
    {
        foreach ([[1405, 1], [1405, 7], [1403, 12], [1404, 12]] as [$jy, $jm]) {
            foreach (JalaliCalendar::month($jy, $jm)['weeks'] as $i => $week) {
                self::assertCount(7, $week, "ماه {$jy}/{$jm} هفتهٔ {$i}");
            }
        }
    }

    public function test_leap_year_esfand_shows_thirty_days(): void
    {
        $leap = JalaliCalendar::month(1403, 12);
        $days = [];
        foreach ($leap['weeks'] as $week) {
            foreach ($week as $cell) {
                if ($cell !== null) {
                    $days[] = $cell['jday'];
                }
            }
        }
        self::assertSame(30, max($days), 'اسفند ۱۴۰۳ کبیسه است و باید ۳۰ روز داشته باشد');
        self::assertCount(30, $days);

        $normal = JalaliCalendar::month(1404, 12);
        $normalDays = 0;
        foreach ($normal['weeks'] as $week) {
            foreach ($week as $cell) {
                if ($cell !== null) {
                    $normalDays++;
                }
            }
        }
        self::assertSame(29, $normalDays, 'اسفند ۱۴۰۴ عادی است');
    }

    public function test_thursday_and_friday_marked_as_weekend(): void
    {
        $grid = JalaliCalendar::month(1405, 7, [], new DateTimeImmutable('2026-09-23'));

        foreach ($grid['weeks'] as $week) {
            foreach ([5, 6] as $weekendColumn) {          // پنج‌شنبه، جمعه
                if ($week[$weekendColumn] !== null) {
                    self::assertTrue($week[$weekendColumn]['isWeekend']);
                }
            }
            foreach ([0, 1, 2, 3, 4] as $workday) {
                if ($week[$workday] !== null) {
                    self::assertFalse($week[$workday]['isWeekend']);
                }
            }
        }
    }

    public function test_month_navigation_wraps_across_year(): void
    {
        self::assertSame(['year' => 1404, 'month' => 12], JalaliCalendar::month(1405, 1)['prev']);
        self::assertSame(['year' => 1406, 'month' => 1], JalaliCalendar::month(1405, 12)['next']);
        self::assertSame(['year' => 1405, 'month' => 6], JalaliCalendar::month(1405, 7)['prev']);
    }

    public function test_past_days_are_not_available_by_default(): void
    {
        $grid = JalaliCalendar::month(1405, 7, [], new DateTimeImmutable('2026-10-01'));

        foreach ($grid['weeks'] as $week) {
            foreach ($week as $cell) {
                if ($cell !== null && $cell['isPast']) {
                    self::assertFalse($cell['available'], "روز گذشته {$cell['gregorian']} نباید قابل انتخاب باشد");
                }
            }
        }
    }

    public function test_day_states_override_availability(): void
    {
        $grid = JalaliCalendar::month(1405, 7, [
            '2026-09-24' => ['available' => false, 'label' => 'تعطیل'],
        ], new DateTimeImmutable('2026-09-23'));

        foreach ($grid['weeks'] as $week) {
            foreach ($week as $cell) {
                if ($cell !== null && $cell['gregorian'] === '2026-09-24') {
                    self::assertFalse($cell['available']);
                    self::assertSame('تعطیل', $cell['label']);

                    return;
                }
            }
        }
        self::fail('روز موردنظر در شبکه پیدا نشد');
    }

    public function test_relative_date_wording(): void
    {
        $today = new DateTimeImmutable('2026-09-20');

        self::assertSame('دیروز', JalaliCalendar::relativeDate(new DateTimeImmutable('2026-09-19'), $today));
        self::assertSame('امروز', JalaliCalendar::relativeDate(new DateTimeImmutable('2026-09-20'), $today));
        self::assertSame('فردا', JalaliCalendar::relativeDate(new DateTimeImmutable('2026-09-21'), $today));
        self::assertSame('پس‌فردا', JalaliCalendar::relativeDate(new DateTimeImmutable('2026-09-22'), $today));
        self::assertStringContainsString('مهر', JalaliCalendar::relativeDate(new DateTimeImmutable('2026-09-30'), $today));
    }

    public function test_human_date_reads_naturally(): void
    {
        // ۲۴ سپتامبر ۲۰۲۶ = پنج‌شنبه ۲ مهر ۱۴۰۵
        self::assertSame('پنج‌شنبه ۲ مهر', JalaliCalendar::humanDate(new DateTimeImmutable('2026-09-24')));
        self::assertSame('پنج‌شنبه ۲ مهر ۱۴۰۵', JalaliCalendar::humanDate(new DateTimeImmutable('2026-09-24'), true));
    }
}
