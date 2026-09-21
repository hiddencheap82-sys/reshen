<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\DB;
use App\Domain\Customer\CustomerAuth;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * ناحیهٔ مشتری — «نوبت‌های من».
 *
 * دو چیز که باید تضمین شوند و هیچ‌کدام در نگاه اول پیدا نیستند:
 * اینکه مشتری نوبت‌های همهٔ آرایشگاه‌هایش را یک‌جا ببیند، و اینکه
 * نوبت کسِ دیگری هرگز در فهرستش نیاید — چون لغو کردن از روی همین
 * فهرست سنجیده می‌شود.
 */
final class CustomerAreaTest extends TestCase
{
    private int $salonA;
    private int $salonB;

    protected function setUp(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach (['appointment_items', 'appointments', 'customers', 'staff', 'salons'] as $t) {
            DB::statement("DELETE FROM `{$t}` WHERE 1");
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->salonA = $this->salon('یکم');
        $this->salonB = $this->salon('دوم');
    }

    private function salon(string $name): int
    {
        return (int) DB::insert('salons', [
            'slug' => 'me-' . bin2hex(random_bytes(4)),
            'name' => 'آرایشگاه ' . $name,
            'is_active' => 1,
        ]);
    }

    private function appointment(int $salonId, string $phone, string $when, string $status): int
    {
        $customerId = (int) DB::insert('customers', [
            'salon_id' => $salonId,
            'name' => 'مشتری',
            'phone' => $phone,
        ]);

        return (int) DB::insert('appointments', [
            'salon_id' => $salonId,
            'public_token' => bin2hex(random_bytes(6)),
            'customer_id' => $customerId,
            'kind' => 'booked',
            'status' => $status,
            'scheduled_at' => $when,
        ]);
    }

    public function test_my_appointments_span_every_salon_i_have_visited(): void
    {
        $phone = '+989120001111';
        $soon = (new DateTimeImmutable('+2 days'))->format('Y-m-d H:i:s');

        $a = $this->appointment($this->salonA, $phone, $soon, 'confirmed');
        $b = $this->appointment($this->salonB, $phone, $soon, 'confirmed');

        $ids = array_map('intval', array_column(CustomerAuth::appointments($phone, true), 'id'));

        sort($ids);
        self::assertSame([min($a, $b), max($a, $b)], $ids, 'هر دو آرایشگاه باید بیایند');
    }

    public function test_another_persons_appointment_never_appears(): void
    {
        $mine = '+989120001111';
        $theirs = '+989129998888';
        $soon = (new DateTimeImmutable('+2 days'))->format('Y-m-d H:i:s');

        $this->appointment($this->salonA, $mine, $soon, 'confirmed');
        $foreign = $this->appointment($this->salonA, $theirs, $soon, 'confirmed');

        $ids = array_map('intval', array_column(CustomerAuth::appointments($mine, true), 'id'));

        self::assertNotContains($foreign, $ids, 'نوبت شمارهٔ دیگری نباید در فهرست من باشد');
    }

    public function test_upcoming_and_past_are_separated(): void
    {
        $phone = '+989120001111';
        $future = $this->appointment($this->salonA, $phone, (new DateTimeImmutable('+3 days'))->format('Y-m-d H:i:s'), 'confirmed');
        $old = $this->appointment($this->salonA, $phone, (new DateTimeImmutable('-3 days'))->format('Y-m-d H:i:s'), 'completed');

        $upcomingIds = array_map('intval', array_column(CustomerAuth::appointments($phone, true), 'id'));
        $pastIds = array_map('intval', array_column(CustomerAuth::appointments($phone, false), 'id'));

        self::assertContains($future, $upcomingIds);
        self::assertNotContains($old, $upcomingIds);

        self::assertContains($old, $pastIds);
        self::assertNotContains($future, $pastIds);
    }

    public function test_a_cancelled_booking_leaves_the_upcoming_list(): void
    {
        $phone = '+989120001111';
        $id = $this->appointment($this->salonA, $phone, (new DateTimeImmutable('+3 days'))->format('Y-m-d H:i:s'), 'cancelled');

        $upcomingIds = array_map('intval', array_column(CustomerAuth::appointments($phone, true), 'id'));

        self::assertNotContains($id, $upcomingIds, 'نوبت لغوشده نباید «پیش رو» باشد');
    }

    public function test_customer_identity_is_not_a_staff_login(): void
    {
        /*
         * مهم‌ترین تستِ این فایل: ورود مشتری نباید از نظر Auth کارکنان
         * «واردشده» حساب شود، وگرنه هر میدل‌وری که فقط ورود را چک کند
         * در پنل آرایشگاه را باز می‌کند.
         */
        CustomerAuth::login('+989120001111');

        self::assertTrue(CustomerAuth::check());
        self::assertFalse(\App\Core\Auth::check(), 'مشتری نباید به‌عنوان کاربر پنل شناخته شود');
        self::assertNull(\App\Core\Auth::id());

        CustomerAuth::logout();
        self::assertFalse(CustomerAuth::check());
    }
}
