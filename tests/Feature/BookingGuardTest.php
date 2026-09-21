<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\DB;
use App\Domain\Booking\BookingGuard;
use PHPUnit\Framework\TestCase;

/**
 * حفاظ رزرو.
 *
 * وقتی کد تأیید برداشته شد (ت-۳۵)، این تنها چیزی است که جلوی ساختن
 * انبوهِ نوبتِ الکی را می‌گیرد. اگر بی‌صدا از کار بیفتد، کسی متوجه
 * نمی‌شود تا روزی که صف پر از نوبت جعلی باشد.
 */
final class BookingGuardTest extends TestCase
{
    private int $salonId;
    private BookingGuard $guard;

    protected function setUp(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach (['appointment_items', 'appointments', 'customers', 'salons'] as $t) {
            DB::statement("DELETE FROM `{$t}` WHERE 1");
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->salonId = (int) DB::insert('salons', [
            'slug' => 'guard-' . bin2hex(random_bytes(4)),
            'name' => 'سالن حفاظ',
            'is_active' => 1,
        ]);
        $this->guard = new BookingGuard();
    }

    public function test_new_phone_is_allowed(): void
    {
        $result = $this->guard->check($this->salonId, '+989120000001', '10.0.0.1');

        self::assertTrue($result['ok']);
        self::assertNull($result['error']);
    }

    /** شمارهٔ ناشناس نباید کوئریِ اضافه بزند یا خطا بدهد. */
    public function test_missing_ip_is_tolerated(): void
    {
        self::assertTrue($this->guard->check($this->salonId, '+989120000002', null)['ok']);
        self::assertTrue($this->guard->check($this->salonId, '+989120000002', '')['ok']);
    }

    public function test_too_many_bookings_from_one_phone_in_a_day_is_blocked(): void
    {
        $phone = '+989120000003';
        $customerId = $this->customer($phone);

        // سقف پیش‌فرض ۵ تا در روز
        for ($i = 0; $i < 5; $i++) {
            $this->appointment($customerId, 'confirmed', '+' . ($i + 1) . ' days', '10.0.0.9');
        }

        $result = $this->guard->check($this->salonId, $phone, '10.0.0.9');

        self::assertFalse($result['ok']);
        self::assertStringContainsString('امروز', $result['error']);
    }

    /** نوبت‌های آیندهٔ هم‌زمان هم سقف دارند. */
    public function test_too_many_open_future_bookings_is_blocked(): void
    {
        $phone = '+989120000004';
        $customerId = $this->customer($phone);

        // سه نوبت آینده، ولی با تاریخ ساختِ قدیمی تا سقف روزانه نخورد
        for ($i = 0; $i < 3; $i++) {
            $id = $this->appointment($customerId, 'confirmed', '+' . ($i + 2) . ' days', null);
            DB::statement('UPDATE appointments SET created_at = NOW() - INTERVAL 5 DAY WHERE id = ?', [$id]);
        }

        $result = $this->guard->check($this->salonId, $phone, null);

        self::assertFalse($result['ok']);
        self::assertStringContainsString('آینده', $result['error']);
    }

    /** نوبت لغوشده نباید جا را اشغال کند. */
    public function test_cancelled_bookings_do_not_count_against_the_open_limit(): void
    {
        $phone = '+989120000005';
        $customerId = $this->customer($phone);

        for ($i = 0; $i < 3; $i++) {
            $id = $this->appointment($customerId, 'cancelled', '+' . ($i + 2) . ' days', null);
            DB::statement('UPDATE appointments SET created_at = NOW() - INTERVAL 5 DAY WHERE id = ?', [$id]);
        }

        self::assertTrue($this->guard->check($this->salonId, $phone, null)['ok']);
    }

    /** نوبت گذشته هم نباید بشمارد — وگرنه مشتری قدیمی برای همیشه بسته می‌شود. */
    public function test_past_bookings_do_not_count_against_the_open_limit(): void
    {
        $phone = '+989120000006';
        $customerId = $this->customer($phone);

        for ($i = 0; $i < 4; $i++) {
            $id = $this->appointment($customerId, 'confirmed', '-' . ($i + 2) . ' days', null);
            DB::statement('UPDATE appointments SET created_at = NOW() - INTERVAL 10 DAY WHERE id = ?', [$id]);
        }

        self::assertTrue($this->guard->check($this->salonId, $phone, null)['ok']);
    }

    /**
     * سقف IP، شماره‌های ساختگی را می‌گیرد.
     *
     * این همان حمله‌ای است که سقفِ شماره جلویش را نمی‌گیرد: اسکریپتی
     * که هر بار شمارهٔ تازه می‌سازد.
     */
    public function test_too_many_bookings_from_one_ip_is_blocked(): void
    {
        $ip = '203.0.113.7';

        // سقف پیش‌فرض ۱۰ تا در ساعت، هر کدام با شمارهٔ متفاوت
        for ($i = 0; $i < 10; $i++) {
            $customerId = $this->customer('+98912100' . str_pad((string) $i, 4, '0', STR_PAD_LEFT));
            $this->appointment($customerId, 'confirmed', '+1 day', $ip);
        }

        $result = $this->guard->check($this->salonId, '+989129999999', $ip);

        self::assertFalse($result['ok']);
        self::assertStringContainsString('دستگاه', $result['error']);
    }

    /** IP دیگری نباید قربانیِ سقفِ همسایه شود. */
    public function test_a_different_ip_is_not_affected(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $customerId = $this->customer('+98912200' . str_pad((string) $i, 4, '0', STR_PAD_LEFT));
            $this->appointment($customerId, 'confirmed', '+1 day', '203.0.113.8');
        }

        self::assertTrue($this->guard->check($this->salonId, '+989128888888', '203.0.113.9')['ok']);
    }

    private function customer(string $phone): int
    {
        return (int) DB::insert('customers', [
            'salon_id' => $this->salonId,
            'name' => 'مشتری',
            'phone' => $phone,
        ]);
    }

    private function appointment(int $customerId, string $status, string $when, ?string $ip): int
    {
        return (int) DB::insert('appointments', [
            'salon_id' => $this->salonId,
            'public_token' => bin2hex(random_bytes(6)),
            'customer_id' => $customerId,
            'kind' => 'booked',
            'status' => $status,
            'scheduled_at' => (new \DateTimeImmutable($when))->format('Y-m-d H:i:s'),
            'created_ip' => $ip,
        ]);
    }
}
