<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Auth;
use App\Core\DB;
use App\Domain\Salon\SalonDashboard;
use PHPUnit\Framework\TestCase;

/**
 * داشبورد سالن — عددها و مرزش.
 *
 * دو چیز اینجا قفل می‌شود:
 *
 * ۱. مرز مستأجری. داشبورد جمعِ پول و شمارِ مشتری را نشان می‌دهد؛ اگر
 *    یک کوئری `salon_id` را جا بیندازد، سالن الف فروش سالن ب را
 *    می‌بیند و هیچ خطایی هم رخ نمی‌دهد. پس هر متد با دو سالنِ هم‌زمان
 *    آزموده می‌شود، نه یکی.
 *
 * ۲. مرز نقش. مسیر داشبورد پشت OwnerManagerRequired است چون درآمد کل
 *    سالن را نشان می‌دهد — چیزی که آرایشگر نباید ببیند.
 */
final class SalonDashboardTest extends TestCase
{
    private int $salonA;
    private int $salonB;

    protected function setUp(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach (['payments', 'appointments', 'customers', 'staff', 'salon_user', 'users', 'salons'] as $t) {
            DB::statement("DELETE FROM `{$t}` WHERE 1");
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        $_SESSION = [];

        $this->salonA = $this->makeSalon('الف');
        $this->salonB = $this->makeSalon('ب');
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        Auth::logout();
    }

    private function makeSalon(string $name): int
    {
        return (int) DB::insert('salons', [
            'slug' => 'dash-' . bin2hex(random_bytes(4)),
            'name' => $name,
            'is_active' => 1,
        ]);
    }

    /** یک نوبتِ تمام‌شده با پرداختش. */
    private function completedVisit(int $salonId, string $date, int $amount, ?int $staffId = null): int
    {
        $customerId = (int) DB::insert('customers', [
            'salon_id' => $salonId,
            'name' => 'مشتری',
            'phone' => '0912' . random_int(1000000, 9999999),
            'created_at' => $date . ' 09:00:00',
        ]);

        $apptId = (int) DB::insert('appointments', [
            'salon_id' => $salonId,
            'public_token' => substr(bin2hex(random_bytes(8)), 0, 12),
            'customer_id' => $customerId,
            'staff_id' => $staffId,
            'kind' => 'booked',
            'status' => 'completed',
            'scheduled_at' => $date . ' 10:00:00',
            'actual_start_at' => $date . ' 10:00:00',
            'actual_end_at' => $date . ' 10:30:00',
            'created_at' => $date . ' 09:00:00',
        ]);

        DB::insert('payments', [
            'salon_id' => $salonId,
            'appointment_id' => $apptId,
            'method' => 'cash',
            'amount' => $amount,
            'paid_at' => $date . ' 10:30:00',
        ]);

        return $apptId;
    }

    public function test_today_counts_only_this_salon(): void
    {
        $today = date('Y-m-d');
        $this->completedVisit($this->salonA, $today, 1_000_000);
        $this->completedVisit($this->salonA, $today, 2_000_000);
        $this->completedVisit($this->salonB, $today, 9_000_000);

        $a = (new SalonDashboard($this->salonA))->today($today);

        self::assertSame(3_000_000, $a['revenue'], 'فروش سالن ب نباید در سالن الف دیده شود');
        self::assertSame(2, $a['appointments']);
        self::assertSame(2, $a['completed']);
        self::assertSame(2, $a['new_customers']);
    }

    public function test_yesterdays_money_is_not_counted_today(): void
    {
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $this->completedVisit($this->salonA, $yesterday, 5_000_000);

        self::assertSame(0, (new SalonDashboard($this->salonA))->today($today)['revenue']);
        self::assertSame(5_000_000, (new SalonDashboard($this->salonA))->today($yesterday)['revenue']);
    }

    /**
     * کارِ تمام‌شده‌ای که پولش ثبت نشده باید دیده شود.
     *
     * این همان حالتی است که گزارش فروش را بی‌صدا کم نشان می‌دهد:
     * هیچ‌جای برنامه خطا نمی‌دهد، فقط عدد غلط است.
     */
    public function test_unpaid_finished_work_shows_up_as_needing_attention(): void
    {
        $today = date('Y-m-d');
        $customerId = (int) DB::insert('customers', [
            'salon_id' => $this->salonA, 'name' => 'بی‌پرداخت', 'phone' => '09120000001',
        ]);
        $apptId = (int) DB::insert('appointments', [
            'salon_id' => $this->salonA,
            'public_token' => substr(bin2hex(random_bytes(8)), 0, 12),
            'customer_id' => $customerId,
            'kind' => 'booked',
            'status' => 'completed',
            'scheduled_at' => $today . ' 11:00:00',
            'actual_start_at' => $today . ' 11:00:00',
            'actual_end_at' => $today . ' 11:30:00',
        ]);

        self::assertSame(1, (new SalonDashboard($this->salonA))->needsAttention($today)['unpaid']);

        // و وقتی پرداختِ *همین* نوبت ثبت شد، از فهرست بیرون می‌رود.
        DB::insert('payments', [
            'salon_id' => $this->salonA,
            'appointment_id' => $apptId,
            'method' => 'cash',
            'amount' => 1_000_000,
            'paid_at' => $today . ' 11:30:00',
        ]);

        self::assertSame(0, (new SalonDashboard($this->salonA))->needsAttention($today)['unpaid']);
    }

    public function test_the_seven_day_chart_keeps_empty_days(): void
    {
        $today = date('Y-m-d');
        $this->completedVisit($this->salonA, $today, 1_000_000);

        $days = (new SalonDashboard($this->salonA))->lastDays($today);

        // هفت خانه، همیشه — وگرنه روزِ تعطیل از نمودار حذف می‌شود و
        // هفته پررونق‌تر از واقعیت دیده می‌شود.
        self::assertCount(7, $days);
        self::assertSame($today, $days[6]['date']);
        self::assertSame(1_000_000, $days[6]['total']);
        self::assertSame(0, $days[0]['total']);

        foreach ($days as $day) {
            self::assertNotSame('', $day['weekday']);
            self::assertNotSame('', $day['label']);
        }
    }

    public function test_staff_totals_stay_inside_the_salon(): void
    {
        $today = date('Y-m-d');
        $staffA = (int) DB::insert('staff', ['salon_id' => $this->salonA, 'name' => 'رضا', 'is_active' => 1]);
        $staffB = (int) DB::insert('staff', ['salon_id' => $this->salonB, 'name' => 'امیر', 'is_active' => 1]);

        $this->completedVisit($this->salonA, $today, 1_500_000, $staffA);
        $this->completedVisit($this->salonB, $today, 8_000_000, $staffB);

        $rows = (new SalonDashboard($this->salonA))->byStaff($today);

        self::assertCount(1, $rows, 'آرایشگر سالن دیگر نباید در فهرست باشد');
        self::assertSame('رضا', $rows[0]['name']);
        self::assertSame(1_500_000, (int) $rows[0]['total']);
    }

    public function test_range_total_is_scoped_and_bounded(): void
    {
        $today = date('Y-m-d');
        $old = date('Y-m-d', strtotime('-40 days'));
        $this->completedVisit($this->salonA, $today, 1_000_000);
        $this->completedVisit($this->salonA, $old, 7_000_000);
        $this->completedVisit($this->salonB, $today, 3_000_000);

        $dash = new SalonDashboard($this->salonA);

        self::assertSame(1_000_000, $dash->rangeTotal(date('Y-m-d', strtotime('-6 days')), $today));
        self::assertSame(8_000_000, $dash->rangeTotal($old, $today));
    }

    /** مشتریِ تک‌مراجعه هیچ‌وقت «خوابیده» شمرده نمی‌شود. */
    public function test_a_one_time_customer_is_never_called_sleeping(): void
    {
        DB::insert('customers', [
            'salon_id' => $this->salonA,
            'name' => 'یک‌بار آمده',
            'phone' => '09120000002',
            'visit_count' => 1,
            'last_visit_at' => date('Y-m-d H:i:s', strtotime('-300 days')),
            'created_at' => date('Y-m-d H:i:s', strtotime('-320 days')),
        ]);

        self::assertSame([], (new SalonDashboard($this->salonA))->sleepingCustomers());
    }

    public function test_a_regular_who_stopped_coming_is_listed(): void
    {
        DB::insert('customers', [
            'salon_id' => $this->salonA,
            'name' => 'همیشگی',
            'phone' => '09120000003',
            // ۱۲ بار در ۳۶۰ روز یعنی هر ۳۳ روز یک بار؛ ۱۲۰ روز غیبت
            // بیش از دو برابرِ همان است.
            'visit_count' => 12,
            'last_visit_at' => date('Y-m-d H:i:s', strtotime('-120 days')),
            'created_at' => date('Y-m-d H:i:s', strtotime('-480 days')),
        ]);

        $sleeping = (new SalonDashboard($this->salonA))->sleepingCustomers();

        self::assertCount(1, $sleeping);
        self::assertSame('همیشگی', $sleeping[0]['name']);
    }

    /** مشتریِ سالن دیگر در فهرست خوابیده‌های این سالن نمی‌آید. */
    public function test_sleeping_customers_are_scoped_to_the_salon(): void
    {
        DB::insert('customers', [
            'salon_id' => $this->salonB,
            'name' => 'مال سالن ب',
            'phone' => '09120000004',
            'visit_count' => 12,
            'last_visit_at' => date('Y-m-d H:i:s', strtotime('-120 days')),
            'created_at' => date('Y-m-d H:i:s', strtotime('-480 days')),
        ]);

        self::assertSame([], (new SalonDashboard($this->salonA))->sleepingCustomers());
    }

    /**
     * مسیر داشبورد باید پشت «صاحب و مدیر» باشد.
     *
     * تست روی *متن مسیرها* است نه روی درخواست HTTP، چون همان‌جاست که
     * اشتباه رخ می‌دهد: یک خط جابه‌جا نوشته شود و مسیر از گروه بیرون
     * بیفتد. آن‌وقت هیچ چیزی نمی‌شکند، فقط آرایشگر درآمد کل سالن را
     * می‌بیند.
     */
    public function test_the_dashboard_route_sits_behind_the_owner_manager_group(): void
    {
        $routes = (string) file_get_contents(BASE_PATH . '/routes/panel.php');

        $guard = strpos($routes, 'OwnerManagerRequired::class]');
        $dashboard = strpos($routes, "'/panel/dashboard'");

        self::assertIsInt($guard);
        self::assertIsInt($dashboard);
        self::assertGreaterThan($guard, $dashboard, 'مسیر داشبورد باید داخل گروه «صاحب و مدیر» باشد');
    }
}
