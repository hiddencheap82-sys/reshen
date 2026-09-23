<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Auth;
use App\Core\DB;
use PHPUnit\Framework\TestCase;

/**
 * هیچ‌کس جز مدیر پلتفرم نباید به پنل پلتفرم برسد.
 *
 * این مرز از بقیهٔ مرزهای برنامه مهم‌تر است: پشتش دادهٔ *همهٔ* سالن‌ها
 * است، بدون محدودیت `salon_id`. یک سوراخ اینجا یعنی صاحب یک سالن،
 * مشتری‌های رقیبش را می‌بیند.
 */
final class PlatformAccessTest extends TestCase
{
    protected function setUp(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach (['salon_user', 'users', 'salons'] as $t) {
            DB::statement("DELETE FROM `{$t}` WHERE 1");
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        Auth::logout();
    }

    private function makeUser(bool $platformAdmin): int
    {
        return (int) DB::insert('users', [
            'phone' => '+98912' . random_int(1000000, 9999999),
            'name' => $platformAdmin ? 'مدیر پلتفرم' : 'کاربر عادی',
            'is_platform_admin' => $platformAdmin ? 1 : 0,
        ]);
    }

    public function test_a_guest_is_not_a_platform_admin(): void
    {
        self::assertFalse(Auth::isPlatformAdmin());
    }

    public function test_a_salon_owner_is_not_a_platform_admin(): void
    {
        // مهم‌ترین حالت: صاحب سالن بالاترین نقش *داخل سالن خودش* را
        // دارد، و همان باعث می‌شود کسی فکر کند دسترسی پلتفرم هم دارد.
        $salonId = (int) DB::insert('salons', [
            'slug' => 'acc-' . bin2hex(random_bytes(4)),
            'name' => 'سالن',
            'is_active' => 1,
        ]);
        $userId = $this->makeUser(false);
        DB::insert('salon_user', [
            'salon_id' => $salonId,
            'user_id' => $userId,
            'role' => 'owner',
        ]);

        Auth::login($userId);
        Auth::setSalon($salonId);

        self::assertTrue(Auth::check());
        self::assertFalse(Auth::isPlatformAdmin(), 'صاحب سالن، مدیر پلتفرم نیست.');
    }

    public function test_a_platform_admin_is_recognised(): void
    {
        Auth::login($this->makeUser(true));

        self::assertTrue(Auth::isPlatformAdmin());
    }

    public function test_the_flag_is_read_fresh_after_it_is_revoked(): void
    {
        // اگر نقش در نشست حافظه بگیرد، کسی که دسترسی‌اش گرفته شده تا
        // خروج و ورود بعدی همچنان مدیر می‌ماند.
        $userId = $this->makeUser(true);
        Auth::login($userId);
        self::assertTrue(Auth::isPlatformAdmin());

        DB::update('users', ['is_platform_admin' => 0], 'id = :id', ['id' => $userId]);
        Auth::logout();
        Auth::login($userId);

        self::assertFalse(Auth::isPlatformAdmin());
    }
}
