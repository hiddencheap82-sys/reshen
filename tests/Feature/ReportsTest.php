<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\DB;
use App\Core\Request;
use App\Http\Controllers\ReportController;
use App\Support\Jalali;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * گزارش‌ها باید با خودشان بخوانند.
 *
 * دو ایرادی که این تست قفل می‌کند، هر دو از آن نوع‌اند که صاحب سالن
 * را به کل گزارش بی‌اعتماد می‌کنند:
 *
 *   - نمودار ماهانه فقط روزهای پرفروش را می‌کشید. ماهی با یک روز
 *     فروش، یک مستطیلِ یکدست به پهنای کارت می‌شد؛ ماهی با فروش در
 *     روز ۳ و ۲۸، دو میله را کنار هم می‌گذاشت.
 *
 *   - «فروش کل» با تاریخ پرداخت حساب می‌شد و سهم آرایشگرها با تاریخ
 *     پایان خدمت. اصلاحی که ۲۳:۵۵ تمام و ۰۰:۰۵ حساب شد، در دو روزِ
 *     مختلف شمرده می‌شد و جمع ردیف‌ها با عدد بالای صفحه نمی‌خواند.
 */
final class ReportsTest extends TestCase
{
    private int $salonId;
    private int $staffA;
    private int $staffB;

    protected function setUp(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach (['payments', 'appointment_items', 'appointments', 'customers', 'staff', 'salons'] as $t) {
            DB::statement("DELETE FROM `{$t}` WHERE 1");
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->salonId = (int) DB::insert('salons', [
            'slug' => 'rep-' . bin2hex(random_bytes(4)),
            'name' => 'سالن گزارش',
            'is_active' => 1,
            'seats' => 2,
        ]);
        $this->staffA = (int) DB::insert('staff', [
            'salon_id' => $this->salonId, 'name' => 'رضا', 'color' => '#2563eb', 'is_active' => 1,
        ]);
        $this->staffB = (int) DB::insert('staff', [
            'salon_id' => $this->salonId, 'name' => 'امیر', 'color' => '#2563eb', 'is_active' => 1,
        ]);

        $_SESSION = ['user_id' => 1, 'salon_id' => $this->salonId];
        $_GET = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_GET = [];
    }

    /** یک نوبتِ انجام‌شده و پرداخت‌شده. */
    private function paid(int $staffId, int $rials, string $endedAt, string $paidAt): void
    {
        $customerId = (int) DB::insert('customers', [
            'salon_id' => $this->salonId, 'name' => 'مشتری',
            'phone' => '+98912' . random_int(1000000, 9999999),
        ]);
        $apptId = (int) DB::insert('appointments', [
            'salon_id' => $this->salonId,
            'public_token' => bin2hex(random_bytes(6)),
            'customer_id' => $customerId,
            'staff_id' => $staffId,
            'kind' => 'walkin',
            'status' => 'completed',
            'queued_at' => $endedAt,
            'actual_start_at' => $endedAt,
            'actual_end_at' => $endedAt,
        ]);
        DB::insert('payments', [
            'salon_id' => $this->salonId,
            'appointment_id' => $apptId,
            'method' => 'cash',
            'amount' => $rials,
            'paid_at' => $paidAt,
        ]);
    }

    /** @return array<string,mixed> داده‌ای که کنترلر به ویو می‌دهد */
    private function render(string $method, array $query = []): array
    {
        $_GET = $query;
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $m = new \ReflectionMethod(ReportController::class, $method . 'Data');
        $m->setAccessible(true);

        return $m->invoke(new ReportController(), new Request());
    }

    // ─── ماهانه ──────────────────────────────────────────────────────

    public function test_the_monthly_chart_has_every_day_of_the_month(): void
    {
        [$jy, $jm] = Jalali::fromDateTime(new DateTimeImmutable('-40 days'));
        $days = $this->render('monthly', ['jy' => (string) $jy, 'jm' => (string) $jm])['days'];

        self::assertCount(Jalali::daysInJalaliMonth($jy, $jm), $days);
        self::assertSame(range(1, count($days)), array_column($days, 'day'));
    }

    /** روزِ بی‌فروش، صفر است — نه غایب. جای هر میله باید معنی داشته باشد. */
    public function test_a_day_without_sales_is_zero_not_missing(): void
    {
        [$jy, $jm] = Jalali::fromDateTime(new DateTimeImmutable('-40 days'));
        [$gy, $gm, $gd] = Jalali::toGregorian($jy, $jm, 3);
        $third = sprintf('%04d-%02d-%02d 12:00:00', $gy, $gm, $gd);
        $this->paid($this->staffA, 2500000, $third, $third);

        $days = $this->render('monthly', ['jy' => (string) $jy, 'jm' => (string) $jm])['days'];

        self::assertSame(2500000, $days[2]['total']);
        self::assertSame(1, $days[2]['visits']);
        self::assertSame(0, $days[3]['total']);
    }

    public function test_the_current_month_has_no_next_link(): void
    {
        self::assertNull($this->render('monthly')['next']);
    }

    // ─── روزانه ──────────────────────────────────────────────────────

    /**
     * اصلاحی که ۲۳:۵۵ دیروز تمام و ۰۰:۰۵ امروز حساب شد: هم در کل امروز
     * است، هم در سهم آرایشگرِ امروز. جمع ردیف‌ها = عدد بالای صفحه.
     */
    public function test_barber_rows_add_up_to_the_headline_across_midnight(): void
    {
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));

        $this->paid($this->staffA, 2500000, $yesterday . ' 23:55:00', $today . ' 00:05:00');
        $this->paid($this->staffB, 1200000, $today . ' 10:00:00', $today . ' 10:05:00');

        $data = $this->render('daily', ['d' => $today]);
        $rows = array_sum(array_map(static fn ($r) => (int) $r['total'], $data['byStaff']));

        self::assertSame((int) $data['totals']['total'], $rows);
        self::assertSame(3700000, $rows);
    }

    public function test_yesterday_is_one_tap_away_and_today_has_no_next(): void
    {
        $data = $this->render('daily');

        self::assertSame(date('Y-m-d', strtotime('-1 day')), $data['prevDate']);
        self::assertNull($data['nextDate']);
        self::assertSame('امروز', $data['dayLabel']);
    }

    /** روزِ آینده گزارشی ندارد؛ به امروز برمی‌گردد نه صفحهٔ خالی. */
    public function test_a_future_day_falls_back_to_today(): void
    {
        $data = $this->render('daily', ['d' => date('Y-m-d', strtotime('+3 days'))]);

        self::assertSame(date('Y-m-d'), $data['date']);
    }
}
