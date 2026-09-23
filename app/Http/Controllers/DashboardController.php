<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Domain\Salon\SalonDashboard;
use DateTimeImmutable;

/**
 * داشبورد سالن — صفحهٔ «امروز در یک نگاه» برای صاحب و مدیر.
 *
 * چرا جای صفحهٔ صف را نمی‌گیرد: صف را آرایشگر وسط کار باز می‌کند و
 * باید در یک نگاه بگوید نفر بعدی کیست. این صفحه را صاحب سالن صبح و
 * شب باز می‌کند. یک صفحه نمی‌تواند هر دو کار را خوب انجام دهد، پس
 * دو صفحه‌اند و نوار ناوبری هر دو را دارد.
 */
final class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $salonId = Auth::salonId();
        $today = date('Y-m-d');

        // تاریخ فقط از انتخابگر شمسی می‌آید؛ نبودنش یعنی امروز.
        $date = jalali_date_from_request($request, 'date') ?? $today;

        $dash = new SalonDashboard($salonId);

        $end = new DateTimeImmutable($date);
        $thisWeekFrom = $end->modify('-6 days')->format('Y-m-d');
        $prevWeekTo = $end->modify('-7 days')->format('Y-m-d');
        $prevWeekFrom = $end->modify('-13 days')->format('Y-m-d');

        return $this->page('layouts.panel', 'panel.dashboard', [
            'title' => 'داشبورد',
            'date' => $date,
            'isToday' => $date === $today,
            'attention' => $dash->needsAttention($date),
            'today' => $dash->today($date),
            // «الان» فقط وقتی معنی دارد که تاریخِ دیده‌شده امروز باشد.
            'rightNow' => $date === $today ? $dash->rightNow() : null,
            'byStaff' => $dash->byStaff($date),
            'days' => $dash->lastDays($date),
            'weekTotal' => $dash->rangeTotal($thisWeekFrom, $date),
            'prevWeekTotal' => $dash->rangeTotal($prevWeekFrom, $prevWeekTo),
            'busiestHours' => $dash->busiestHours($date),
            'sleeping' => $dash->sleepingCustomers(),
        ]);
    }
}
