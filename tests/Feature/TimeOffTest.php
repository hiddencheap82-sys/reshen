<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\DB;
use App\Domain\Booking\BookingService;
use App\Domain\Booking\SlotFinder;
use App\Domain\Staff\TimeOffRepository;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * مرخصی و بستنِ موردیِ بازه.
 *
 * جدولش از اول بود و SlotFinder می‌خواندش، ولی هیچ صفحه‌ای برای ساختن
 * ردیف نداشت — قابلیتی که کار می‌کرد و دست کسی نمی‌رسید. این تست هم
 * ساختِ ردیف را می‌بندد، هم اثرش روی سانس‌ها را، تا دوباره بی‌صدا از
 * کار نیفتد.
 */
final class TimeOffTest extends TestCase
{
    private int $salonId;
    private int $staffId;
    private string $date;

    protected function setUp(): void
    {
        SlotFinder::flushCache();

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach (['time_offs', 'appointment_items', 'appointments', 'customers',
                  'working_hours', 'services', 'staff', 'salons', 'holidays'] as $t) {
            DB::statement("DELETE FROM `{$t}` WHERE 1");
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->salonId = (int) DB::insert('salons', [
            'slug' => 'off-' . bin2hex(random_bytes(4)),
            'name' => 'سالن مرخصی',
            'is_active' => 1,
            'slot_step_minutes' => 30,
        ]);

        $this->staffId = (int) DB::insert('staff', [
            'salon_id' => $this->salonId, 'name' => 'آرایشگر', 'is_active' => 1,
        ]);

        // یک روز آینده که تعطیل آخر هفته نباشد
        $d = new DateTimeImmutable('+3 days');
        while (in_array((int) $d->format('N'), [4, 5], true)) {
            $d = $d->modify('+1 day');
        }
        $this->date = $d->format('Y-m-d');

        $weekday = \App\Support\Jalali::weekday($d);
        DB::insert('working_hours', [
            'salon_id' => $this->salonId,
            'staff_id' => null,
            'weekday' => $weekday,
            'opens_at' => '09:00:00',
            'closes_at' => '17:00:00',
            'is_closed' => 0,
        ]);

        SlotFinder::flushCache();
    }

    private function freeSlots(): int
    {
        SlotFinder::flushCache();
        $booking = new BookingService();

        return count($booking->freeSlots(
            $this->salonId,
            null,
            new DateTimeImmutable($this->date),
            $booking->sessionMinutes($this->salonId)
        ));
    }

    public function test_closing_the_whole_day_removes_every_slot(): void
    {
        $before = $this->freeSlots();
        self::assertGreaterThan(0, $before, 'روز آزمایشی باید سانس داشته باشد');

        (new TimeOffRepository())->add(
            $this->salonId,
            null,
            new DateTimeImmutable($this->date . ' 00:00:00'),
            (new DateTimeImmutable($this->date . ' 00:00:00'))->modify('+1 day'),
            'کل روز'
        );

        self::assertSame(0, $this->freeSlots());
    }

    public function test_closing_part_of_the_day_removes_only_those_slots(): void
    {
        $before = $this->freeSlots();

        (new TimeOffRepository())->add(
            $this->salonId,
            null,
            new DateTimeImmutable($this->date . ' 13:00:00'),
            new DateTimeImmutable($this->date . ' 15:00:00'),
            'ناهار طولانی'
        );

        $after = $this->freeSlots();
        self::assertGreaterThan(0, $after, 'بقیهٔ روز باید باز بماند');
        self::assertLessThan($before, $after, 'سانس‌های آن بازه باید بسته شوند');
    }

    public function test_a_time_off_for_another_salons_staff_is_refused(): void
    {
        $otherSalon = (int) DB::insert('salons', [
            'slug' => 'other-' . bin2hex(random_bytes(4)), 'name' => 'سالن دیگر', 'is_active' => 1,
        ]);
        $foreignStaff = (int) DB::insert('staff', [
            'salon_id' => $otherSalon, 'name' => 'غریبه', 'is_active' => 1,
        ]);

        $error = (new TimeOffRepository())->add(
            $this->salonId,
            $foreignStaff,
            new DateTimeImmutable($this->date . ' 10:00:00'),
            new DateTimeImmutable($this->date . ' 11:00:00'),
            null
        );

        self::assertNotNull($error, 'شناسهٔ دستکاری‌شده نباید آرایشگر سالن دیگر را مرخصی بزند');
        self::assertSame(0, (int) (DB::selectOne('SELECT COUNT(*) c FROM time_offs')['c'] ?? -1));
    }

    public function test_an_end_before_the_start_is_refused(): void
    {
        $error = (new TimeOffRepository())->add(
            $this->salonId,
            null,
            new DateTimeImmutable($this->date . ' 15:00:00'),
            new DateTimeImmutable($this->date . ' 13:00:00'),
            null
        );

        self::assertNotNull($error);
    }

    public function test_it_names_the_appointments_caught_inside_the_closure(): void
    {
        $customerId = (int) DB::insert('customers', [
            'salon_id' => $this->salonId, 'name' => 'مشتری', 'phone' => '+989120000000',
        ]);
        DB::insert('appointments', [
            'salon_id' => $this->salonId,
            'public_token' => bin2hex(random_bytes(6)),
            'customer_id' => $customerId,
            'staff_id' => $this->staffId,
            'kind' => 'booked',
            'status' => 'confirmed',
            'scheduled_at' => $this->date . ' 14:00:00',
        ]);

        $clashes = (new TimeOffRepository())->clashingAppointments(
            $this->salonId,
            null,
            new DateTimeImmutable($this->date . ' 13:00:00'),
            new DateTimeImmutable($this->date . ' 15:00:00')
        );

        self::assertCount(1, $clashes, 'نوبتِ داخل بازه باید به صاحب سالن گفته شود');
        self::assertSame('مشتری', $clashes[0]['customer_name']);
    }
}
