<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Domain\Payment\PaymentRepository;
use App\Support\Jalali;
use DateTimeImmutable;

/** D04/D05/D12 — daily & monthly sales, and the "گزارش نجات‌یافته‌ها" retention report. */
final class ReportController extends Controller
{
    public function daily(Request $request): Response
    {
        return $this->page('layouts.panel', 'panel.reports.daily', $this->dailyData($request));
    }

    /**
     * داده‌های گزارش روزانه — جدا از رندر تا تست بتواند مستقیم بسنجدش.
     *
     * @return array<string,mixed>
     */
    private function dailyData(Request $request): array
    {
        $salonId = Auth::salonId();

        /*
         * روز: اول `?d=` (پیوندهای روز قبل و بعد)، بعد انتخابگر شمسی
         * (برای پریدن به روزی دور)، و گرنه امروز. روزِ آینده گزارشی
         * ندارد، پس به امروز برمی‌گردد.
         */
        $today = date('Y-m-d');
        $d = (string) $request->query('d', '');
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) === 1 && strtotime($d) !== false
            ? $d
            : (jalali_date_from_request($request, 'date') ?? $today);
        if ($date > $today) {
            $date = $today;
        }

        $payments = new PaymentRepository();

        $totals = $payments->dailyTotal($salonId, $date);
        $breakdown = $payments->methodBreakdown($salonId, $date, $date);

        /*
         * سهم هر آرایشگر — با همان مبنای تاریخی که عددِ «فروش کل» دارد.
         *
         * پیش‌تر کل با تاریخِ پرداخت حساب می‌شد و سهم آرایشگرها با
         * تاریخِ پایانِ خدمت. اصلاحی که ۲۳:۵۵ تمام شده و ۰۰:۰۵ حساب شده،
         * در کل امروز بود و در سهم دیروز — جمع ردیف‌ها با عدد بالای صفحه
         * نمی‌خواند، و گزارشی که جمعش نخواند، دیگر باور نمی‌شود.
         */
        $byStaff = DB::select(
            "SELECT st.id, st.name,
                    COUNT(DISTINCT x.appointment_id) AS count,
                    COALESCE(SUM(x.amount), 0) AS total
               FROM staff st
               LEFT JOIN (
                    SELECT p.appointment_id, p.amount, a.staff_id
                      FROM payments p
                      JOIN appointments a ON a.id = p.appointment_id
                     WHERE p.salon_id = ? AND DATE(p.paid_at) = ?
               ) x ON x.staff_id = st.id
              WHERE st.salon_id = ?
              GROUP BY st.id, st.name
              ORDER BY total DESC, st.name",
            [$salonId, $date, $salonId]
        );

        $rescued = $this->rescuedReport($salonId, $date, $date);

        $at = new DateTimeImmutable($date);

        return [
            'title' => 'گزارش روزانه',
            'date' => $date,
            'dayLabel' => \App\Support\JalaliCalendar::relativeDate($at),
            'prevDate' => $at->modify('-1 day')->format('Y-m-d'),
            'nextDate' => $date < $today ? $at->modify('+1 day')->format('Y-m-d') : null,
            'isToday' => $date === $today,
            'totals' => $totals,
            'breakdown' => $breakdown,
            'byStaff' => $byStaff,
            'rescued' => $rescued,
        ];
    }

    public function monthly(Request $request): Response
    {
        return $this->page('layouts.panel', 'panel.reports.monthly', $this->monthlyData($request));
    }

    /**
     * داده‌های گزارش ماهانه — جدا از رندر، به همان دلیلِ dailyData.
     *
     * @return array<string,mixed>
     */
    private function monthlyData(Request $request): array
    {
        $salonId = Auth::salonId();
        $jy = (int) $request->query('jy', Jalali::fromDateTime(new DateTimeImmutable())[0]);
        $jm = (int) $request->query('jm', Jalali::fromDateTime(new DateTimeImmutable())[1]);

        [$gy1, $gm1, $gd1] = Jalali::toGregorian($jy, $jm, 1);
        $daysInMonth = Jalali::daysInJalaliMonth($jy, $jm);
        [$gy2, $gm2, $gd2] = Jalali::toGregorian($jy, $jm, $daysInMonth);
        $from = sprintf('%04d-%02d-%02d', $gy1, $gm1, $gd1);
        $to = sprintf('%04d-%02d-%02d', $gy2, $gm2, $gd2);

        $payments = new PaymentRepository();
        $totals = $payments->rangeTotal($salonId, $from, $to);
        $breakdown = $payments->methodBreakdown($salonId, $from, $to);
        $rescued = $this->rescuedReport($salonId, $from, $to);

        $byDate = [];
        foreach (DB::select(
            "SELECT DATE(paid_at) AS d, COALESCE(SUM(amount),0) AS total,
                    COUNT(DISTINCT appointment_id) AS visits
               FROM payments
              WHERE salon_id = ? AND DATE(paid_at) BETWEEN ? AND ?
              GROUP BY DATE(paid_at)",
            [$salonId, $from, $to]
        ) as $row) {
            $byDate[(string) $row['d']] = $row;
        }

        /*
         * همهٔ روزهای ماه، نه فقط روزهایی که فروش داشته‌اند.
         *
         * پیش‌تر نمودار فقط روزهای پرفروش را می‌کشید، هرکدام با
         * flex-1. ماهی که یک روز فروش داشت، یک مستطیلِ نارنجیِ یکدست به
         * پهنای کل کارت می‌شد؛ و ماهی که روز ۳ و روز ۲۸ فروش داشت، دو
         * میله را کنار هم می‌گذاشت، انگار روزهای پشت‌سرهم‌اند. محور
         * روزها باید ثابت باشد تا جای هر میله *معنی* داشته باشد.
         */
        $today = date('Y-m-d');
        $days = [];
        for ($d = 1; $d <= $daysInMonth; $d++) {
            [$gy, $gm, $gd] = Jalali::toGregorian($jy, $jm, $d);
            $date = sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
            $days[] = [
                'day' => $d,
                'date' => $date,
                'total' => (int) ($byDate[$date]['total'] ?? 0),
                'visits' => (int) ($byDate[$date]['visits'] ?? 0),
                'isToday' => $date === $today,
                'isFuture' => $date > $today,
            ];
        }

        // ماه قبل و بعد؛ ماهِ آینده پیوند نمی‌گیرد چون چیزی برای نشان دادن ندارد.
        [$prevY, $prevM] = $jm === 1 ? [$jy - 1, 12] : [$jy, $jm - 1];
        [$nextY, $nextM] = $jm === 12 ? [$jy + 1, 1] : [$jy, $jm + 1];
        [$nowJy, $nowJm] = Jalali::fromDateTime(new DateTimeImmutable());
        $hasNext = $nextY < $nowJy || ($nextY === $nowJy && $nextM <= $nowJm);

        return [
            'title' => 'گزارش ماهانه',
            'jy' => $jy,
            'jm' => $jm,
            'totals' => $totals,
            'breakdown' => $breakdown,
            'rescued' => $rescued,
            'days' => $days,
            'prev' => ['jy' => $prevY, 'jm' => $prevM],
            'next' => $hasNext ? ['jy' => $nextY, 'jm' => $nextM] : null,
        ];
    }

    /**
     * D12 — "گزارش نجات‌یافته‌ها": appointments a reminder/nearly-up SMS
     * plausibly saved from becoming a no-show, valued conservatively at the
     * appointment's own price — this is what makes renewal an easy call
     * for the owner, so it must stay defensible, never inflated.
     */
    private function rescuedReport(int $salonId, string $from, string $to): array
    {
        $rows = DB::select(
            "SELECT COUNT(DISTINCT a.id) AS count, COALESCE(SUM(ai.price),0) AS value
             FROM appointments a
             JOIN sms_messages sm ON sm.appointment_id = a.id AND sm.template_code IN ('reminder_24h','reminder_2h') AND sm.status = 'sent'
             JOIN appointment_items ai ON ai.appointment_id = a.id
             WHERE a.salon_id = ? AND a.status = 'completed' AND DATE(a.actual_end_at) BETWEEN ? AND ?",
            [$salonId, $from, $to]
        );

        return $rows[0] ?? ['count' => 0, 'value' => 0];
    }
}
