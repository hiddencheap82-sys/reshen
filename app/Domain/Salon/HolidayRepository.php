<?php

declare(strict_types=1);

namespace App\Domain\Salon;

use App\Core\DB;
use App\Support\Jalali;

/**
 * Official Iranian holidays. Only the *fixed-date* solar-Hijri holidays
 * (Nowruz, Sizdah be-dar, revolution anniversaries, ...) can be computed
 * programmatically. Lunar-Hijri holidays (Eid Fitr, Ashura, Ramadan, ...)
 * shift ~11 days a year and need an Umm al-Qura conversion this build does
 * not include — those are added manually from the settings screen instead.
 */
final class HolidayRepository
{
    /** [jalali month, jalali day, label] */
    private const FIXED_HOLIDAYS = [
        [1, 1, 'نوروز'],
        [1, 2, 'نوروز'],
        [1, 3, 'نوروز'],
        [1, 4, 'نوروز'],
        [1, 12, 'روز جمهوری اسلامی'],
        [1, 13, 'سیزده‌به‌در'],
        [3, 14, 'رحلت امام خمینی'],
        [3, 15, 'قیام ۱۵ خرداد'],
        [11, 22, 'پیروزی انقلاب اسلامی'],
        [12, 29, 'ملی‌شدن صنعت نفت'],
    ];

    public function seedFixedHolidaysForYear(int $jalaliYear): void
    {
        foreach (self::FIXED_HOLIDAYS as [$jm, $jd, $label]) {
            [$gy, $gm, $gd] = Jalali::toGregorian($jalaliYear, $jm, $jd);
            $date = sprintf('%04d-%02d-%02d', $gy, $gm, $gd);

            $exists = DB::selectOne('SELECT id FROM holidays WHERE gregorian_date = ?', [$date]);
            if ($exists === null) {
                DB::insert('holidays', ['gregorian_date' => $date, 'jalali_label' => $label, 'is_official' => 1]);
            }
        }
    }

    public function between(string $fromDate, string $toDate): array
    {
        return DB::select(
            'SELECT * FROM holidays WHERE gregorian_date BETWEEN ? AND ? ORDER BY gregorian_date',
            [$fromDate, $toDate]
        );
    }

    public function isHoliday(string $gregorianDate): bool
    {
        return DB::selectOne('SELECT id FROM holidays WHERE gregorian_date = ?', [$gregorianDate]) !== null;
    }

    public function add(string $gregorianDate, string $label): void
    {
        DB::insert('holidays', ['gregorian_date' => $gregorianDate, 'jalali_label' => $label, 'is_official' => 0]);
    }

    public function remove(int $id): void
    {
        DB::delete('holidays', 'id = ?', [$id]);
    }

    public function upcoming(int $limit = 10): array
    {
        return DB::select('SELECT * FROM holidays WHERE gregorian_date >= CURDATE() ORDER BY gregorian_date LIMIT ?', [$limit]);
    }
}
