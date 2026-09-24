<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Auth;
use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Domain\Appointment\AppointmentRepository;
use App\Domain\Queue\EtaEngine;
use App\Http\Controllers\QueueController;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * حلقهٔ اصلیِ آرایشگر: شروع ← تمام شد ← تسویه.
 *
 * چرا این تست هست: این سه ضربه، روزی صدها بار زده می‌شوند و هر گیری
 * در آن‌ها، هر بار تکرار می‌شود. سه گیرِ واقعی اینجا قفل شده‌اند:
 *
 *   ۱. آرایشگرِ بدون دسترسیِ تسویه، بعد از «تمام شد» به ‎/panel/pay‎
 *      فرستاده می‌شد — که برایش ۴۰۳ است. هر مشتری، یک صفحهٔ «دسترسی
 *      ندارید».
 *   ۲. کاری که او تمام می‌کرد و پولش ثبت نمی‌شد، هیچ‌جا فهرست نبود.
 *      داشبورد «۲ نوبت تسویه‌نشده» می‌گفت و به صفحه‌ای لینک می‌داد که
 *      چیزی برای تسویه نداشت.
 *   ۳. دکمهٔ «شروع» رنگ نداشت: کلاسِ رنگ‌دهنده جدا بود و جا مانده بود.
 */
final class BarberLoopTest extends TestCase
{
    private int $salonId;
    private int $serviceId;
    private int $staffId;
    private int $ownerId;
    private int $barberUserId;

    protected function setUp(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach (['payments', 'appointment_items', 'appointments', 'customers', 'staff',
                  'services', 'salon_user', 'users', 'salons'] as $t) {
            DB::statement("DELETE FROM `{$t}` WHERE 1");
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->salonId = (int) DB::insert('salons', [
            'slug' => 'loop-' . bin2hex(random_bytes(4)), 'name' => 'سالن', 'is_active' => 1, 'seats' => 2,
        ]);
        $this->serviceId = (int) DB::insert('services', [
            'salon_id' => $this->salonId, 'name' => 'اصلاح مو',
            'duration_minutes' => 30, 'price' => 2500000, 'is_active' => 1,
        ]);

        $this->ownerId = (int) DB::insert('users', ['phone' => '+989120000100', 'name' => 'صاحب']);
        DB::insert('salon_user', ['salon_id' => $this->salonId, 'user_id' => $this->ownerId, 'role' => 'owner', 'is_active' => 1]);

        $this->barberUserId = (int) DB::insert('users', ['phone' => '+989120000200', 'name' => 'رضا']);
        DB::insert('salon_user', ['salon_id' => $this->salonId, 'user_id' => $this->barberUserId, 'role' => 'staff', 'is_active' => 1]);

        $this->staffId = (int) DB::insert('staff', [
            'salon_id' => $this->salonId, 'user_id' => $this->barberUserId,
            'name' => 'رضا', 'color' => '#2563eb', 'is_active' => 1,
        ]);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        Auth::forgetUserCache();
    }

    private function actAs(int $userId): void
    {
        $_SESSION = ['user_id' => $userId, 'salon_id' => $this->salonId];
        Auth::forgetUserCache();
    }

    private function appointment(string $status, ?string $name = 'مهدی'): int
    {
        $customerId = (int) DB::insert('customers', [
            'salon_id' => $this->salonId, 'name' => $name, 'phone' => '+98912' . random_int(1000000, 9999999),
        ]);
        $id = (int) DB::insert('appointments', [
            'salon_id' => $this->salonId, 'public_token' => bin2hex(random_bytes(6)),
            'customer_id' => $customerId, 'staff_id' => $this->staffId,
            'kind' => 'walkin', 'status' => $status,
            'queued_at' => date('Y-m-d H:i:s', time() - 3600),
            'actual_start_at' => $status === 'queued' ? null : date('Y-m-d H:i:s', time() - 1800),
            'actual_end_at' => $status === 'completed' ? date('Y-m-d H:i:s', time() - 60) : null,
        ]);
        DB::insert('appointment_items', [
            'salon_id' => $this->salonId, 'appointment_id' => $id,
            'service_id' => $this->serviceId, 'price' => 2500000, 'duration_minutes' => 30,
        ]);

        return $id;
    }

    private function complete(int $appointmentId): Response
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $request = new Request();
        $request->routeParams = ['id' => (string) $appointmentId];

        return (new QueueController())->complete($request);
    }

    // ─── «تمام شد» ───────────────────────────────────────────────────

    public function test_a_barber_without_payment_access_goes_back_to_the_queue_not_to_a_403(): void
    {
        $id = $this->appointment('in_chair');
        $this->actAs($this->barberUserId);

        $response = $this->complete($id);

        self::assertSame(url('panel'), $response->headers['Location'] ?? null);
        self::assertStringContainsString('پیشخوان', (string) ($_SESSION['_flash']['success'] ?? ''));
        self::assertSame('completed', DB::selectOne('SELECT status FROM appointments WHERE id = ?', [$id])['status']);
    }

    public function test_the_owner_goes_straight_to_payment_with_the_amount(): void
    {
        $id = $this->appointment('in_chair');
        $this->actAs($this->ownerId);

        $response = $this->complete($id);

        self::assertSame(url('panel/pay/' . $id . '?amount=2500000'), $response->headers['Location'] ?? null);
    }

    // ─── منتظر تسویه ─────────────────────────────────────────────────

    public function test_completed_unpaid_work_is_listed_for_the_front_desk(): void
    {
        $unpaid = $this->appointment('completed', 'علی');
        $paid = $this->appointment('completed', 'حسن');
        DB::insert('payments', [
            'salon_id' => $this->salonId, 'appointment_id' => $paid, 'method' => 'cash',
            'amount' => 2500000, 'tip_amount' => 0, 'paid_at' => date('Y-m-d H:i:s'),
        ]);
        $this->appointment('in_chair', 'هنوز روی صندلی');

        $rows = (new AppointmentRepository())->awaitingPayment($this->salonId);

        self::assertSame([$unpaid], array_column($rows, 'id'));
        self::assertSame('علی', $rows[0]['customer_name']);
        self::assertSame('رضا', $rows[0]['staff_name']);
        self::assertSame(2500000, $rows[0]['total']);
    }

    /** عددِ داشبورد و ردیف‌های صف یکی‌اند — از داشبورد که بیایی، همان را می‌بینی. */
    public function test_the_list_matches_the_dashboard_count(): void
    {
        $this->appointment('completed');
        $this->appointment('completed');

        $count = (new \App\Domain\Salon\SalonDashboard($this->salonId))->needsAttention(date('Y-m-d'))['unpaid'];

        self::assertSame($count, count((new AppointmentRepository())->awaitingPayment($this->salonId)));
    }

    // ─── مراجعهٔ حضوری ───────────────────────────────────────────────

    public function test_adding_a_walk_in_answers_how_long(): void
    {
        $this->appointment('in_chair', 'نفرِ روی صندلی');
        $this->actAs($this->ownerId);
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['name' => 'پیمان', 'phone' => '', 'staff_id' => (string) $this->staffId,
                  'service_ids' => [(string) $this->serviceId]];

        (new QueueController())->addWalkin(new Request());

        $message = (string) ($_SESSION['_flash']['success'] ?? '');
        self::assertStringContainsString('«پیمان»', $message);
        self::assertStringContainsString('رضا', $message);
        // صندلی پر است، پس زمان دارد — نه فقط «اضافه شد».
        self::assertStringContainsString('نوبتش', $message);
    }

    public function test_a_walk_in_on_a_free_chair_is_seated_right_away(): void
    {
        $this->actAs($this->ownerId);
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['name' => '', 'phone' => '', 'staff_id' => (string) $this->staffId,
                  'service_ids' => [(string) $this->serviceId]];

        (new QueueController())->addWalkin(new Request());

        self::assertSame('مشتری روی صندلیِ رضا نشست.', $_SESSION['_flash']['success'] ?? null);
    }

    // ─── صندلی و رزرو ───────────────────────────────────────────────

    private function booking(string $scheduledAt): int
    {
        $customerId = (int) DB::insert('customers', [
            'salon_id' => $this->salonId, 'name' => 'رزروی', 'phone' => '+98912' . random_int(1000000, 9999999),
        ]);
        $id = (int) DB::insert('appointments', [
            'salon_id' => $this->salonId, 'public_token' => bin2hex(random_bytes(6)),
            'customer_id' => $customerId, 'staff_id' => $this->staffId,
            'kind' => 'booked', 'status' => 'confirmed', 'scheduled_at' => $scheduledAt,
        ]);
        DB::insert('appointment_items', [
            'salon_id' => $this->salonId, 'appointment_id' => $id,
            'service_id' => $this->serviceId, 'price' => 2500000, 'duration_minutes' => 30,
        ]);

        return $id;
    }

    private function walkIn(): array
    {
        return (new \App\Domain\Queue\QueueService())->addWalkin($this->salonId, 'حضوری', null, $this->staffId, [$this->serviceId]);
    }

    private function statusOf(int $id): string
    {
        return (string) DB::selectOne('SELECT status FROM appointments WHERE id = ?', [$id])['status'];
    }

    /**
     * رزروِ هفت دقیقهٔ دیگر، صندلی را نگه می‌دارد.
     *
     * همان صحنه‌ای که نصبِ تازه نشان داد: حضوریِ ۱۲:۰۸ روی صندلی نشست و
     * مشتریِ رزروِ ۱۲:۱۵ باید ۲۳ دقیقه منتظر می‌ماند، در حالی که تخمینِ
     * صف رزرو را اول نشان می‌داد.
     */
    public function test_a_walk_in_is_not_seated_over_a_booking_that_is_due(): void
    {
        $this->booking(date('Y-m-d H:i:s', time() + 7 * 60));

        $walkIn = $this->walkIn();

        self::assertSame('queued', $this->statusOf((int) $walkIn['id']));
    }

    public function test_a_walk_in_is_seated_when_the_next_booking_is_hours_away(): void
    {
        if ((int) date('H') >= 21) {
            self::markTestSkipped('رزروِ دو ساعت بعد به فردا می‌افتد.');
        }
        $this->booking(date('Y-m-d H:i:s', time() + 2 * 3600));

        $walkIn = $this->walkIn();

        self::assertSame('in_chair', $this->statusOf((int) $walkIn['id']));
    }

    /** غیبتِ ثبت‌نشده صندلی را قفل نمی‌کند. */
    public function test_a_late_booking_does_not_block_the_chair(): void
    {
        if ((int) date('H') < 1) {
            self::markTestSkipped('رزروِ یک ساعت پیش مالِ دیروز است.');
        }
        $this->booking(date('Y-m-d H:i:s', time() - 3600));

        $walkIn = $this->walkIn();

        self::assertSame('in_chair', $this->statusOf((int) $walkIn['id']));
    }

    /** بعد از «تمام شد» هم همین قاعده: نفرِ بعدیِ حاضر، مگر رزرو نوبتش باشد. */
    public function test_finishing_does_not_seat_a_walk_in_over_a_due_booking(): void
    {
        $inChair = $this->appointment('in_chair');
        $waiting = $this->appointment('queued', 'منتظر');
        $this->booking(date('Y-m-d H:i:s', time() + 5 * 60));

        (new \App\Domain\Queue\QueueService())->completeService($this->salonId, $inChair);

        self::assertSame('queued', $this->statusOf($waiting));
    }

    // ─── زبانِ پنل ──────────────────────────────────────────────────

    /** «نوبت بعدی توست» جملهٔ مشتری است؛ پنل از پرچمش «نفر بعدی» می‌سازد. */
    public function test_the_next_up_estimate_is_flagged_for_the_panel(): void
    {
        $now = new DateTimeImmutable();
        $engine = new EtaEngine();

        self::assertTrue($engine->displayText(0, $now, $now, $now)['next'] ?? false);
        self::assertArrayNotHasKey('next', $engine->displayText(1, $now->modify('+30 minutes'), $now->modify('+40 minutes'), $now));
    }

    // ─── دکمه ───────────────────────────────────────────────────────

    /**
     * دکمهٔ اصلی رنگِ خودش را دارد.
     *
     * پیش‌تر پرشدگی را ‎.metal‎ می‌داد و هرجا جا می‌ماند، دکمه متنِ
     * بی‌رنگ می‌شد — از جمله «شروع» روی هر ردیفِ صف.
     */
    public function test_the_primary_button_is_filled_on_its_own(): void
    {
        $css = (string) file_get_contents(BASE_PATH . '/tools/assets/src.css');

        self::assertMatchesRegularExpression('/\.btn-accent\{[^}]*background:var\(--accent\)/s', $css);
        self::assertMatchesRegularExpression('/\.btn-tint\{[^}]*background:var\(--accent-soft\)/s', $css);
    }
}
