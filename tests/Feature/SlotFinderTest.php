<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\DB;
use App\Domain\Booking\SlotFinder;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * سانس‌های قابل رزرو.
 *
 * چرا با دیتابیس واقعی: منطق سانس به ساعت کاری، استراحت و نوبت‌های
 * موجود وابسته است و همه در دیتابیس‌اند. تستی که این‌ها را جعل کند،
 * چیزی را که در عمل می‌شکند نمی‌گیرد (تصمیم ت-۰۸).
 */
final class SlotFinderTest extends TestCase
{
    private int $salonId;
    private int $staffId;

    protected function setUp(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach (['appointment_items', 'appointments', 'working_hours', 'staff', 'salons'] as $t) {
            DB::statement("DELETE FROM `{$t}` WHERE 1");
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->salonId = (int) DB::insert('salons', [
            'slug' => 'test-' . bin2hex(random_bytes(4)),
            'name' => 'سالن آزمون',
            'is_active' => 1,
            'slot_step_minutes' => 15,
        ]);

        $this->staffId = (int) DB::insert('staff', [
            'salon_id' => $this->salonId,
            'name' => 'آرایشگر آزمون',
            'is_active' => 1,
        ]);
    }

    /** ساعت کاری برای یک روز هفته — weekday صفر=شنبه. */
    private function hours(int $weekday, string $opens, string $closes,
                          ?string $breakStart = null, ?string $breakEnd = null): void
    {
        DB::insert('working_hours', [
            'salon_id' => $this->salonId,
            'staff_id' => $this->staffId,
            'weekday' => $weekday,
            'opens_at' => $opens,
            'closes_at' => $closes,
            'break_start' => $breakStart,
            'break_end' => $breakEnd,
            'is_closed' => 0,
        ]);
    }

    /** یک تاریخ آینده که روز هفته‌اش مشخص باشد. */
    private function futureDate(int $weekday): DateTimeImmutable
    {
        $d = (new DateTimeImmutable('tomorrow'))->setTime(0, 0);
        for ($i = 0; $i < 8; $i++) {
            if (\App\Support\Jalali::weekday($d) === $weekday) {
                return $d;
            }
            $d = $d->modify('+1 day');
        }
        self::fail('روز هفتهٔ موردنظر پیدا نشد');
    }

    public function test_slots_follow_opening_hours(): void
    {
        $date = $this->futureDate(1);
        $this->hours(1, '09:00:00', '12:00:00');

        $slots = (new SlotFinder())->freeSlotsForStaff($this->salonId, $this->staffId, $date, 30);

        self::assertSame('09:00', $slots[0], 'اولین سانس باید ساعت باز شدن باشد');
        self::assertSame('11:30', end($slots), 'آخرین سانس باید جا داشته باشد تا پیش از بسته شدن تمام شود');
        self::assertNotContains('11:45', $slots, 'سانسی که تا بعد از بسته شدن طول بکشد نباید باشد');
    }

    public function test_break_time_is_not_bookable(): void
    {
        $date = $this->futureDate(2);
        $this->hours(2, '09:00:00', '18:00:00', '13:00:00', '14:00:00');

        $slots = (new SlotFinder())->freeSlotsForStaff($this->salonId, $this->staffId, $date, 30);

        foreach (['12:45', '13:00', '13:15', '13:30'] as $blocked) {
            self::assertNotContains($blocked, $slots, "{$blocked} در استراحت است و نباید قابل رزرو باشد");
        }

        self::assertContains('12:30', $slots, 'پیش از استراحت باید آزاد باشد');
        self::assertContains('14:00', $slots, 'بلافاصله پس از استراحت باید آزاد باشد');
    }

    public function test_slot_step_comes_from_salon_setting(): void
    {
        $date = $this->futureDate(3);
        $this->hours(3, '09:00:00', '11:00:00');

        DB::update('salons', ['slot_step_minutes' => 30], 'id = :id', ['id' => $this->salonId]);

        // کش ایستا در SlotFinder برای هر نمونه جداست
        $slots = (new SlotFinder())->freeSlotsForStaff($this->salonId, $this->staffId, $date, 30);

        self::assertSame(['09:00', '09:30', '10:00', '10:30'], $slots);
    }

    public function test_closed_day_has_no_slots(): void
    {
        $date = $this->futureDate(4);
        DB::insert('working_hours', [
            'salon_id' => $this->salonId, 'staff_id' => $this->staffId,
            'weekday' => 4, 'opens_at' => '09:00:00', 'closes_at' => '18:00:00',
            'is_closed' => 1,
        ]);

        self::assertSame([], (new SlotFinder())->freeSlotsForStaff($this->salonId, $this->staffId, $date, 30));
    }

    /** ساعت کاری سطحِ سالن (staff_id = NULL) — همانی که صفحهٔ تنظیمات می‌نویسد. */
    private function salonHours(int $weekday, string $opens, string $closes,
                                ?string $breakStart = null, ?string $breakEnd = null): void
    {
        DB::insert('working_hours', [
            'salon_id' => $this->salonId,
            'staff_id' => null,
            'weekday' => $weekday,
            'opens_at' => $opens,
            'closes_at' => $closes,
            'break_start' => $breakStart,
            'break_end' => $breakEnd,
            'is_closed' => 0,
        ]);
    }

    /**
     * استراحتِ سالن نباید با ثبت ساعت اختصاصی برای آرایشگر از بین برود.
     *
     * رگرسیون: پیش‌تر SlotFinder ردیف آرایشگر را جایگزین کاملِ ردیف سالن
     * می‌کرد. چون ردیف آرایشگر استراحت نداشت، تعطیلی ظهرِ سالن بی‌صدا
     * نادیده گرفته می‌شد و مشتری وسط استراحت نوبت می‌گرفت.
     */
    public function test_salon_break_survives_staff_specific_hours(): void
    {
        $date = $this->futureDate(1);

        // سالن: ظهر تعطیل. آرایشگر: فقط ساعت خودش، بدون استراحت.
        $this->salonHours(1, '09:00:00', '18:00:00', '13:00:00', '14:00:00');
        $this->hours(1, '10:00:00', '17:00:00');

        $slots = (new SlotFinder())->freeSlotsForStaff($this->salonId, $this->staffId, $date, 30);

        self::assertSame('10:00', $slots[0], 'ساعت باز شدنِ آرایشگر باید برنده باشد');
        self::assertSame('16:30', end($slots), 'ساعت بسته شدنِ آرایشگر باید برنده باشد');

        foreach (['12:45', '13:00', '13:30'] as $blocked) {
            self::assertNotContains($blocked, $slots, "استراحت سالن باید {$blocked} را ببندد");
        }
        self::assertContains('14:00', $slots, 'پس از استراحت باید باز شود');
    }

    /** اگر خودِ آرایشگر استراحت داشته باشد، همان معتبر است نه استراحت سالن. */
    public function test_staff_own_break_wins_over_salon_break(): void
    {
        $date = $this->futureDate(2);

        $this->salonHours(2, '09:00:00', '18:00:00', '13:00:00', '14:00:00');
        $this->hours(2, '09:00:00', '18:00:00', '16:00:00', '17:00:00');

        $slots = (new SlotFinder())->freeSlotsForStaff($this->salonId, $this->staffId, $date, 30);

        self::assertContains('13:00', $slots, 'استراحت سالن نباید اعمال شود چون آرایشگر استراحت خودش را دارد');
        self::assertNotContains('16:00', $slots, 'استراحت خودِ آرایشگر باید اعمال شود');
    }

    public function test_invalid_step_is_clamped_not_infinite(): void
    {
        $date = $this->futureDate(5);
        $this->hours(5, '09:00:00', '10:00:00');

        // صفر، حلقه را بی‌نهایت می‌کرد اگر حفاظ نبود
        DB::update('salons', ['slot_step_minutes' => 0], 'id = :id', ['id' => $this->salonId]);

        $slots = (new SlotFinder())->freeSlotsForStaff($this->salonId, $this->staffId, $date, 30);

        self::assertNotEmpty($slots);
        self::assertLessThan(20, count($slots), 'طول سانس نامعتبر نباید سانس‌های بی‌شمار بسازد');
    }
}
