<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\DB;
use App\Domain\Access\Access;
use PHPUnit\Framework\TestCase;

/**
 * ماتریس دسترسی — چه کسی چه کاری می‌تواند بکند.
 *
 * این تست به این دلیل هست که ایرادهای دسترسی بی‌صدا هستند: همه‌چیز
 * کار می‌کند، فقط یک نفرِ اشتباه دارد کار می‌کند. هیچ خطایی در لاگ
 * نمی‌افتد و تا وقتی کسی شاکی نشود، معلوم نمی‌شود.
 */
final class AccessControlTest extends TestCase
{
    private int $salonId;

    protected function setUp(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach (['appointments', 'salon_user', 'staff', 'users', 'salons'] as $t) {
            DB::statement("DELETE FROM `{$t}` WHERE 1");
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->salonId = (int) DB::insert('salons', [
            'slug' => 'acl-' . bin2hex(random_bytes(4)),
            'name' => 'سالن دسترسی',
            'is_active' => 1,
        ]);
    }

    protected function tearDown(): void
    {
        $this->actAs(null);
    }

    /** نشستن به جای یک نقش، بدون رفتن به لایهٔ HTTP. */
    private function actAs(?string $role): ?int
    {
        if ($role === null) {
            $_SESSION = [];
            \App\Core\Auth::logout();

            return null;
        }

        $userId = (int) DB::insert('users', [
            'phone' => '+98912' . str_pad((string) random_int(1000000, 9999999), 7, '0', STR_PAD_LEFT),
            'name' => $role,
        ]);
        DB::insert('salon_user', [
            'salon_id' => $this->salonId,
            'user_id' => $userId,
            'role' => $role,
            'is_active' => 1,
        ]);

        \App\Core\Auth::logout();
        \App\Core\Auth::login($userId);
        \App\Core\Auth::setSalon($this->salonId);

        return $userId;
    }

    public function test_owner_and_manager_can_do_everything(): void
    {
        foreach (['owner', 'manager'] as $role) {
            $this->actAs($role);
            foreach ([
                Access::MANAGE_SALON,
                Access::VIEW_CUSTOMERS,
                Access::TAKE_PAYMENT,
                Access::BOOK_FOR_OTHERS,
                Access::VIEW_SALON_EARNINGS,
            ] as $ability) {
                self::assertTrue(Access::allows($ability), "{$role} باید {$ability} را داشته باشد");
            }
        }
    }

    public function test_reception_runs_the_front_desk_but_not_the_salon(): void
    {
        $this->actAs('reception');

        self::assertTrue(Access::allows(Access::VIEW_CUSTOMERS));
        self::assertTrue(Access::allows(Access::TAKE_PAYMENT));
        self::assertTrue(Access::allows(Access::BOOK_FOR_OTHERS));

        self::assertFalse(Access::allows(Access::MANAGE_SALON), 'پذیرش نباید تنظیمات سالن را عوض کند');
        self::assertFalse(Access::allows(Access::VIEW_SALON_EARNINGS), 'پذیرش نباید درآمد کل را ببیند');
    }

    public function test_barber_sees_only_their_own_work(): void
    {
        $this->actAs('staff');

        self::assertFalse(Access::allows(Access::VIEW_CUSTOMERS), 'آرایشگر نباید شمارهٔ همهٔ مشتری‌ها را ببیند');
        self::assertFalse(Access::allows(Access::TAKE_PAYMENT));
        self::assertFalse(Access::allows(Access::BOOK_FOR_OTHERS));
        self::assertFalse(Access::allows(Access::MANAGE_SALON));
        self::assertFalse(Access::allows(Access::VIEW_SALON_EARNINGS));
    }

    public function test_barber_cannot_act_on_another_barbers_appointment(): void
    {
        $userId = $this->actAs('staff');

        $mine = (int) DB::insert('staff', [
            'salon_id' => $this->salonId, 'name' => 'خودم', 'user_id' => $userId, 'is_active' => 1,
        ]);
        $theirs = (int) DB::insert('staff', [
            'salon_id' => $this->salonId, 'name' => 'همکار', 'is_active' => 1,
        ]);

        self::assertTrue(Access::canActOnAppointment(['staff_id' => $mine]));
        self::assertFalse(Access::canActOnAppointment(['staff_id' => $theirs]), 'نوبتِ همکار نباید دست آرایشگر باشد');
        self::assertFalse(Access::canActOnAppointment(['staff_id' => null]), 'نوبت بدون آرایشگر هم نه');
    }

    public function test_reception_may_act_on_any_appointment(): void
    {
        $this->actAs('reception');
        $someone = (int) DB::insert('staff', [
            'salon_id' => $this->salonId, 'name' => 'هر کسی', 'is_active' => 1,
        ]);

        self::assertTrue(Access::canActOnAppointment(['staff_id' => $someone]));
    }

    public function test_an_unknown_ability_is_denied_not_granted(): void
    {
        $this->actAs('owner');
        self::assertFalse(Access::allows('غلط_املایی'), 'اجازهٔ ناشناخته باید بسته باشد، نه باز');
    }

    public function test_a_logged_out_visitor_has_nothing(): void
    {
        $this->actAs(null);

        foreach ([Access::MANAGE_SALON, Access::VIEW_CUSTOMERS, Access::TAKE_PAYMENT] as $ability) {
            self::assertFalse(Access::allows($ability));
        }
    }
}
