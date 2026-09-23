<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\DB;
use App\Domain\Platform\SalonHealth;
use PHPUnit\Framework\TestCase;

/**
 * سلامت سالن‌ها.
 *
 * چرا این تست هست: خرابی‌هایی که اینجا سنجیده می‌شوند همه بی‌صدایند.
 * سالنی که آرایشگر فعال ندارد هیچ خطایی نمی‌دهد — صفحهٔ عمومی‌اش باز
 * می‌شود، خوشگل هم هست، فقط هیچ سانسی ندارد. اگر این سنجش خودش
 * بشکند، هیچ‌کس نمی‌فهمد؛ فقط صفحهٔ سلامت می‌گوید «همه‌چیز خوب است»
 * در حالی که نیست. یعنی دقیقاً همان شکستِ بی‌صدایی که قرار بود جلویش
 * را بگیرد.
 */
final class SalonHealthTest extends TestCase
{
    private int $salonId;

    protected function setUp(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach (['platform_invoices', 'sms_messages', 'appointments', 'working_hours',
                  'services', 'staff', 'salon_user', 'customers', 'users', 'salons'] as $t) {
            DB::statement("DELETE FROM `{$t}` WHERE 1");
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->salonId = (int) DB::insert('salons', [
            'slug' => 'health-' . bin2hex(random_bytes(4)),
            'name' => 'سالن سنجش',
            'is_active' => 1,
            'plan_code' => 'trial',
            'seats' => 1,
            'trial_ends_at' => date('Y-m-d H:i:s', strtotime('+10 days')),
        ]);
    }

    /** سالنی که همه‌چیزش سرجایش است. */
    private function makeHealthy(): void
    {
        DB::insert('staff', [
            'salon_id' => $this->salonId, 'name' => 'رضا',
            'color' => '#2563eb', 'is_active' => 1,
        ]);
        DB::insert('services', [
            'salon_id' => $this->salonId, 'name' => 'اصلاح مو',
            'duration_minutes' => 30, 'price' => 2500000, 'is_active' => 1,
        ]);
        for ($d = 0; $d <= 5; $d++) {
            DB::insert('working_hours', [
                'salon_id' => $this->salonId, 'staff_id' => null, 'weekday' => $d,
                'opens_at' => '09:00:00', 'closes_at' => '21:00:00', 'is_closed' => 0,
            ]);
        }

        $userId = (int) DB::insert('users', ['phone' => '+98912' . random_int(1000000, 9999999)]);
        DB::insert('salon_user', [
            'salon_id' => $this->salonId, 'user_id' => $userId,
            'role' => 'owner', 'is_active' => 1,
        ]);
    }

    /** @return array<int,string> عنوان ایرادها */
    private function titles(): array
    {
        return array_column((new SalonHealth())->forSalon($this->salonId), 'title');
    }

    /** @return array<int,string> */
    private function levels(): array
    {
        return array_column((new SalonHealth())->forSalon($this->salonId), 'level');
    }

    // ─── سه ایرادِ کشنده ─────────────────────────────────────────────

    public function test_a_fully_set_up_salon_has_nothing_wrong(): void
    {
        $this->makeHealthy();

        // ساخته‌شدنش تازه است، پس «هیچ نوبتی ثبت نکرده» هنوز نباید
        // بیاید — سالنی که امروز ساخته شده هنوز فرصت نداشته.
        self::assertSame([], $this->titles());
    }

    public function test_a_salon_with_no_active_staff_is_blocking(): void
    {
        $this->makeHealthy();
        DB::statement('UPDATE staff SET is_active = 0 WHERE salon_id = ?', [$this->salonId]);

        self::assertContains('هیچ آرایشگر فعالی ندارد', $this->titles());
        self::assertContains(SalonHealth::BLOCKING, $this->levels());
    }

    public function test_a_salon_with_no_active_service_is_blocking(): void
    {
        $this->makeHealthy();
        DB::statement('UPDATE services SET is_active = 0 WHERE salon_id = ?', [$this->salonId]);

        self::assertContains('هیچ خدمت فعالی ندارد', $this->titles());
    }

    public function test_a_salon_closed_every_day_is_blocking(): void
    {
        $this->makeHealthy();
        DB::statement('UPDATE working_hours SET is_closed = 1 WHERE salon_id = ?', [$this->salonId]);

        self::assertContains('هیچ روز بازی ندارد', $this->titles());
    }

    /**
     * ساعت اختصاصیِ یک آرایشگر، ساعت کاری سالن نیست.
     *
     * جدول یکی است و اگر شرطِ `staff_id IS NULL` جا بیفتد، سالنی که
     * فقط ساعتِ شخصیِ یک آرایشگر را دارد «باز» شمرده می‌شود در حالی
     * که `SlotFinder` هیچ سانسی برایش نمی‌سازد.
     */
    public function test_staff_specific_hours_do_not_count_as_salon_hours(): void
    {
        $this->makeHealthy();
        $staffId = (int) DB::selectOne('SELECT id FROM staff WHERE salon_id = ?', [$this->salonId])['id'];

        DB::statement('DELETE FROM working_hours WHERE salon_id = ?', [$this->salonId]);
        DB::insert('working_hours', [
            'salon_id' => $this->salonId, 'staff_id' => $staffId, 'weekday' => 0,
            'opens_at' => '09:00:00', 'closes_at' => '21:00:00', 'is_closed' => 0,
        ]);

        self::assertContains('هیچ روز بازی ندارد', $this->titles());
    }

    public function test_a_salon_without_an_owner_is_blocking(): void
    {
        $this->makeHealthy();
        DB::statement('DELETE FROM salon_user WHERE salon_id = ?', [$this->salonId]);

        self::assertContains('صاحب یا مدیری ندارد', $this->titles());
    }

    /**
     * سالن بسته‌شده، ایرادِ راه‌اندازی ندارد.
     *
     * عمدی است: غیرفعال کردن یک تصمیم است، نه خرابی. اگر این شرط
     * نباشد، صفحهٔ سلامت پر می‌شود از سالن‌هایی که خودمان بسته‌ایم و
     * آن‌وقت خرابی‌های واقعی لای آن‌ها گم می‌شوند.
     */
    public function test_an_inactive_salon_is_only_informational(): void
    {
        $this->makeHealthy();
        DB::statement('UPDATE staff SET is_active = 0 WHERE salon_id = ?', [$this->salonId]);
        DB::statement('UPDATE salons SET is_active = 0 WHERE id = ?', [$this->salonId]);

        self::assertSame([SalonHealth::INFO], $this->levels());
        self::assertContains('غیرفعال شده', $this->titles());
    }

    // ─── هشدارها ─────────────────────────────────────────────────────

    public function test_an_expired_trial_is_a_warning(): void
    {
        $this->makeHealthy();
        DB::statement(
            'UPDATE salons SET trial_ends_at = ? WHERE id = ?',
            [date('Y-m-d H:i:s', strtotime('-2 days')), $this->salonId]
        );

        self::assertContains('دورهٔ آزمایش تمام شده', $this->titles());
    }

    public function test_a_paid_plan_past_its_trial_date_is_not_flagged(): void
    {
        $this->makeHealthy();
        DB::statement(
            "UPDATE salons SET plan_code = 'salon', trial_ends_at = ? WHERE id = ?",
            [date('Y-m-d H:i:s', strtotime('-60 days')), $this->salonId]
        );

        self::assertNotContains('دورهٔ آزمایش تمام شده', $this->titles());
    }

    public function test_a_salon_that_stopped_booking_is_flagged(): void
    {
        $this->makeHealthy();
        $customerId = (int) DB::insert('customers', [
            'salon_id' => $this->salonId, 'name' => 'مشتری', 'phone' => '+989120000000',
        ]);
        DB::insert('appointments', [
            'salon_id' => $this->salonId,
            'public_token' => bin2hex(random_bytes(6)),
            'customer_id' => $customerId,
            'kind' => 'booked', 'status' => 'completed',
            'scheduled_at' => date('Y-m-d H:i:s', strtotime('-40 days')),
            'created_at' => date('Y-m-d H:i:s', strtotime('-40 days')),
        ]);

        $titles = implode(' | ', $this->titles());
        self::assertStringContainsString('روز است نوبتی ثبت نکرده', $titles);
    }

    /**
     * سالنی که هرگز نوبتی نداشته با سالنی که داشته و قطع شده فرق
     * دارد — و جمله‌شان هم باید فرق کند، چون کاری که باید کرد فرق
     * می‌کند: یکی راه‌اندازیِ نیمه‌کاره است، یکی مشتریِ در حال رفتن.
     */
    public function test_a_salon_that_never_booked_gets_a_different_message(): void
    {
        $this->makeHealthy();
        DB::statement(
            'UPDATE salons SET created_at = ? WHERE id = ?',
            [date('Y-m-d H:i:s', strtotime('-10 days')), $this->salonId]
        );

        self::assertContains('هنوز هیچ نوبتی ثبت نکرده', $this->titles());
    }

    public function test_failed_sms_is_a_warning(): void
    {
        $this->makeHealthy();
        DB::insert('sms_messages', [
            'salon_id' => $this->salonId,
            'to_phone' => '+989120000000',
            'template_code' => 'reminder_24h',
            'body' => 'x',
            'status' => 'failed',
        ]);

        $titles = implode(' | ', $this->titles());
        self::assertStringContainsString('پیامک ناموفق', $titles);
    }

    // ─── مرتب‌سازی و خلاصه ───────────────────────────────────────────

    /** بدترین ایراد باید اول بیاید، وگرنه لای هشدارها گم می‌شود. */
    public function test_blocking_issues_come_first(): void
    {
        $this->makeHealthy();
        DB::statement('UPDATE staff SET is_active = 0 WHERE salon_id = ?', [$this->salonId]);
        DB::statement(
            'UPDATE salons SET trial_ends_at = ? WHERE id = ?',
            [date('Y-m-d H:i:s', strtotime('-2 days')), $this->salonId]
        );

        self::assertSame(SalonHealth::BLOCKING, $this->levels()[0]);
    }

    public function test_the_summary_counts_each_salon_once(): void
    {
        $this->makeHealthy();
        DB::statement('UPDATE staff SET is_active = 0 WHERE salon_id = ?', [$this->salonId]);

        $summary = (new SalonHealth())->summary();

        self::assertSame(1, $summary['blocking']);
        self::assertSame(0, $summary['warning']);
        self::assertSame(0, $summary['healthy']);
    }

    /** فیلترِ «فقط ایراددارها» سالنِ سالم را نشان ندهد. */
    public function test_only_broken_filter_hides_healthy_salons(): void
    {
        $this->makeHealthy();

        self::assertSame([], (new SalonHealth())->all(true));
        self::assertCount(1, (new SalonHealth())->all(false));
    }
}
