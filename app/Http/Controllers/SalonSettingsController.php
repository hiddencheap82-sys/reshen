<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Support\Theme;
use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Domain\Salon\HolidayRepository;
use App\Domain\Salon\WorkingHoursRepository;
use App\Support\Jalali;

final class SalonSettingsController extends Controller
{
    public function show(Request $request): Response
    {
        $salonId = Auth::salonId();
        $salon = DB::selectOne('SELECT * FROM salons WHERE id = ?', [$salonId]);
        $hours = (new WorkingHoursRepository())->salonDefaults($salonId);
        $holidayRepo = new HolidayRepository();
        $upcomingHolidays = $holidayRepo->upcoming(8);

        return $this->page('layouts.panel', 'panel.settings.index', [
            'title' => 'تنظیمات سالن',
            'salon' => $salon,
            'hours' => $hours,
            'weekdayNames' => ['شنبه', 'یک‌شنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنج‌شنبه', 'جمعه'],
            'holidays' => $upcomingHolidays,
            'currentJalaliYear' => Jalali::fromDateTime(new \DateTimeImmutable())[0],
        ]);
    }

    public function updateProfile(Request $request): Response
    {
        DB::update('salons', [
            'name' => trim((string) $request->input('name', '')),
            'city' => trim((string) $request->input('city', '')) ?: null,
            'address' => trim((string) $request->input('address', '')) ?: null,
            'phone' => trim((string) $request->input('phone', '')) ?: null,
            // Theme::resolve مقدار ناشناخته را به پیش‌فرض برمی‌گرداند، پس
            // چیزی جز پالت‌های تعریف‌شده در دیتابیس نمی‌نشیند.
            'theme' => Theme::resolve((string) $request->input('theme', '')),
        ], 'id = :id', ['id' => Auth::salonId()]);

        // نشست را تازه کن وگرنه پنل تا ورود بعدی رنگ قبلی را نشان می‌دهد
        Auth::setSalon(Auth::salonId());

        return $this->withSuccess('اطلاعات سالن ذخیره شد.', '/panel/settings');
    }

    public function updateHours(Request $request): Response
    {
        $repo = new WorkingHoursRepository();
        for ($weekday = 0; $weekday <= 6; $weekday++) {
            $closed = $request->input("closed_$weekday") !== null;
            $opens = (string) $request->input("opens_$weekday", '09:00');
            $closes = (string) $request->input("closes_$weekday", '21:00');
            $breakStart = (string) $request->input("break_start_$weekday", '');
            $breakEnd = (string) $request->input("break_end_$weekday", '');
            $repo->setSalonDay(Auth::salonId(), $weekday, $opens, $closes, $closed, $breakStart, $breakEnd);
        }

        // طول سانس: بین ۵ تا ۱۲۰ دقیقه. صفر، حلقهٔ تولید سانس را
        // بی‌نهایت می‌کند؛ SlotFinder هم حفاظ دارد ولی بهتر است مقدار
        // معیوب اصلاً ذخیره نشود.
        $step = (int) $request->input('slot_step_minutes', 15);
        DB::update('salons', ['slot_step_minutes' => max(5, min(120, $step ?: 15))],
            'id = :id', ['id' => Auth::salonId()]);

        return $this->withSuccess('ساعت کاری و سانس‌بندی ذخیره شد.', '/panel/settings');
    }

    public function seedHolidays(Request $request): Response
    {
        $year = (int) $request->input('jalali_year');
        (new HolidayRepository())->seedFixedHolidaysForYear($year);

        return $this->withSuccess('تعطیلات رسمی ثابت اضافه شد.', '/panel/settings');
    }

    public function addHoliday(Request $request): Response
    {
        $date = (string) $request->input('date');
        $label = trim((string) $request->input('label', 'تعطیل'));
        if ($date !== '') {
            (new HolidayRepository())->add($date, $label);
        }

        return $this->withSuccess('تعطیلی اضافه شد.', '/panel/settings');
    }

    public function removeHoliday(Request $request): Response
    {
        (new HolidayRepository())->remove((int) $request->param('id'));

        return $this->withSuccess('حذف شد.', '/panel/settings');
    }
}
