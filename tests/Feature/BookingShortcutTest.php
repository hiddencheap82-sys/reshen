<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\DB;
use App\Core\Request;
use App\Http\Controllers\BookingWizardController;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * گامِ «آرایشگر» فقط وقتی پرسیده می‌شود که انتخابی باشد.
 *
 * چرا این تست هست: هر صفحهٔ اضافه در مسیر رزرو، بخشی از مشتری‌ها را
 * می‌ریزد — و بیشتر آرایشگاه‌های مردانه یک یا دو صندلی دارند. پرسیدنِ
 * «کدام آرایشگر؟» وقتی فقط یک نفر آزاد است، یک صفحهٔ کامل است با یک
 * گزینه.
 *
 * ولی رد کردنش خطرِ خودش را دارد: اگر وقتی *دو نفر* آزادند هم رد شود،
 * مشتری‌ای که آرایشگر خاصی می‌خواست بی‌خبر به نفر دیگری می‌خورد. هر
 * دو طرف اینجا قفل می‌شوند.
 */
final class BookingShortcutTest extends TestCase
{
    private int $salonId;
    private string $slug;
    private int $serviceId;
    private string $date;

    protected function setUp(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach (['appointment_items', 'appointments', 'customers', 'working_hours',
                  'staff', 'services', 'holidays', 'salons'] as $t) {
            DB::statement("DELETE FROM `{$t}` WHERE 1");
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->slug = 'short-' . bin2hex(random_bytes(4));
        $this->salonId = (int) DB::insert('salons', [
            'slug' => $this->slug,
            'name' => 'سالن میان‌بر',
            'is_active' => 1,
            'seats' => 3,
        ]);

        // هر هفت روز باز — تا تست به اینکه امروز چه روزی است بستگی نداشته باشد.
        for ($d = 0; $d <= 6; $d++) {
            DB::insert('working_hours', [
                'salon_id' => $this->salonId, 'staff_id' => null, 'weekday' => $d,
                'opens_at' => '09:00:00', 'closes_at' => '21:00:00', 'is_closed' => 0,
            ]);
        }

        $this->serviceId = (int) DB::insert('services', [
            'salon_id' => $this->salonId, 'name' => 'اصلاح مو',
            'duration_minutes' => 30, 'price' => 2500000, 'is_active' => 1,
        ]);

        // سه روز بعد، ساعت ده صبح — همیشه آینده، همیشه داخل ساعت کاری.
        $this->date = (new DateTimeImmutable('+3 days'))->format('Y-m-d');

        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    private function addStaff(string $name): int
    {
        return (int) DB::insert('staff', [
            'salon_id' => $this->salonId, 'name' => $name,
            'color' => '#2563eb', 'is_active' => 1,
        ]);
    }

    /** آرایشگری را در همان ساعت مشغول می‌کند. */
    private function makeBusy(int $staffId): void
    {
        $customerId = (int) DB::insert('customers', [
            'salon_id' => $this->salonId, 'name' => 'مشتری دیگر', 'phone' => '+989120000000',
        ]);
        $apptId = (int) DB::insert('appointments', [
            'salon_id' => $this->salonId,
            'public_token' => bin2hex(random_bytes(6)),
            'customer_id' => $customerId,
            'staff_id' => $staffId,
            'kind' => 'booked',
            'status' => 'confirmed',
            'scheduled_at' => $this->date . ' 10:00:00',
        ]);
        DB::insert('appointment_items', [
            'salon_id' => $this->salonId, 'appointment_id' => $apptId,
            'service_id' => $this->serviceId, 'price' => 2500000, 'duration_minutes' => 30,
        ]);
    }

    /**
     * گامِ خدمت را مثل مرورگر ارسال می‌کند و مقصدِ بعدی را برمی‌گرداند.
     */
    private function submitServices(): string
    {
        $_SESSION['booking_' . $this->slug] = ['date' => $this->date, 'time' => '10:00'];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['service_ids' => [(string) $this->serviceId]];

        $request = new Request();
        $request->routeParams = ['slug' => $this->slug];

        $response = (new BookingWizardController())->servicesStep($request);

        return (string) ($response->headers['Location'] ?? '');
    }

    /** @return array<string,mixed> */
    private function wizard(): array
    {
        return $_SESSION['booking_' . $this->slug] ?? [];
    }

    // ─── رد شدن ──────────────────────────────────────────────────────

    public function test_a_one_barber_salon_skips_straight_to_the_phone_step(): void
    {
        $only = $this->addStaff('حسن');

        $next = $this->submitServices();

        self::assertStringEndsWith('/s/' . $this->slug . '/phone', $next);
        self::assertSame($only, $this->wizard()['staff_id']);
        self::assertTrue($this->wizard()['staff_skipped']);
    }

    /**
     * سالنِ چندآرایشگری، ولی در آن ساعت فقط یکی آزاد است. آنجا هم
     * انتخابی نیست — «هرکسی» و «همان یک نفر» یکی‌اند.
     */
    public function test_when_only_one_of_several_is_free_the_step_is_skipped(): void
    {
        $busy = $this->addStaff('رضا');
        $free = $this->addStaff('امیر');
        $this->makeBusy($busy);

        $next = $this->submitServices();

        self::assertStringEndsWith('/phone', $next);
        self::assertSame($free, $this->wizard()['staff_id']);
    }

    // ─── رد نشدن ─────────────────────────────────────────────────────

    /**
     * دو نفر آزادند: این یک انتخاب واقعی است و باید پرسیده شود.
     *
     * اگر اینجا هم رد شود، مشتری‌ای که فقط به رضا اعتماد دارد بی‌خبر
     * به امیر می‌خورد.
     */
    public function test_when_two_are_free_the_customer_is_asked(): void
    {
        $this->addStaff('رضا');
        $this->addStaff('امیر');

        $next = $this->submitServices();

        self::assertStringEndsWith('/s/' . $this->slug . '/staff', $next);
        self::assertFalse($this->wizard()['staff_skipped']);
    }

    /**
     * آرایشگرِ قبلی با رفتن به گام آرایشگر پاک می‌شود.
     *
     * مشتری‌ای که از خلاصهٔ گام آخر برگشته و ساعت را عوض کرده، شاید
     * آرایشگرِ قبلی‌اش در ساعتِ تازه آزاد نباشد. اگر انتخاب قدیمی
     * می‌ماند، ثبتِ نهایی با «این بازه دیگر آزاد نیست» او را به اول
     * مسیر پرت می‌کرد.
     */
    public function test_a_stale_barber_choice_is_cleared(): void
    {
        $this->addStaff('رضا');
        $this->addStaff('امیر');
        $_SESSION['booking_' . $this->slug] = ['staff_id' => 999999];

        $this->submitServices();

        self::assertNull($this->wizard()['staff_id']);
    }

    public function test_when_nobody_is_free_the_customer_goes_back_to_pick_a_time(): void
    {
        $only = $this->addStaff('حسن');
        $this->makeBusy($only);

        $next = $this->submitServices();

        self::assertStringEndsWith('/s/' . $this->slug, $next);
    }

    // ─── نوار پیشرفت ─────────────────────────────────────────────────

    /**
     * نواری که چهار گام بگوید و سه گام برود، مشتری را منتظرِ گامی
     * می‌گذارد که نمی‌آید.
     */
    public function test_a_one_barber_salon_shows_three_steps(): void
    {
        $this->addStaff('حسن');

        self::assertCount(3, $this->stepTitles());
        self::assertNotContains('آرایشگر', $this->stepTitles());
    }

    public function test_a_multi_barber_salon_shows_four_steps(): void
    {
        $this->addStaff('رضا');
        $this->addStaff('امیر');

        self::assertContains('آرایشگر', $this->stepTitles());
        self::assertCount(4, $this->stepTitles());
    }

    /** @return string[] */
    private function stepTitles(): array
    {
        $m = new ReflectionMethod(BookingWizardController::class, 'stepTitles');
        $m->setAccessible(true);

        return $m->invoke(new BookingWizardController(), ['id' => $this->salonId]);
    }
}
