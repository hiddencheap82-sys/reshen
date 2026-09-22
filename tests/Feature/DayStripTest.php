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
 * نوار روزهای صفحهٔ رزرو.
 *
 * دو رفتار اینجا قفل می‌شوند، و هر دو در لحظه‌ای اهمیت پیدا می‌کنند که
 * مشتری واقعاً می‌خواهد نوبت بگیرد:
 *
 * ۱) شمارِ سانس آزاد هر روز درست باشد. اگر «۱۲ سانس» بنویسد و روز پر
 *    باشد، مشتری دو بار کلیک می‌کند و به صفحهٔ خالی می‌رسد.
 *
 * ۲) صفحه روی روزی باز شود که واقعاً وقت دارد. سالن ساعت ۹ شب دیگر
 *    سانسی ندارد؛ پیش از این، مشتری‌ای که همان موقع لینک را باز
 *    می‌کرد با «این روز سانس آزادی ندارد» روبه‌رو می‌شد.
 */
final class DayStripTest extends TestCase
{
    private int $salonId;
    private int $staffId;

    protected function setUp(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach (['appointment_items', 'appointments', 'customers', 'working_hours',
                  'staff', 'services', 'holidays', 'salons'] as $t) {
            DB::statement("DELETE FROM `{$t}` WHERE 1");
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->salonId = (int) DB::insert('salons', [
            'slug' => 'strip-' . bin2hex(random_bytes(4)),
            'name' => 'سالن نوار',
            'is_active' => 1,
            'seats' => 1,
        ]);

        $this->staffId = (int) DB::insert('staff', [
            'salon_id' => $this->salonId,
            'name' => 'آرایشگر',
            'is_active' => 1,
        ]);
    }

    /** ساعت کاری یک روز هفته را باز یا بسته می‌کند. */
    private function setHours(int $weekday, ?string $open, ?string $close): void
    {
        DB::statement(
            'DELETE FROM working_hours WHERE salon_id = ? AND weekday = ? AND staff_id IS NULL',
            [$this->salonId, $weekday]
        );
        DB::insert('working_hours', [
            'salon_id' => $this->salonId,
            'staff_id' => null,
            'weekday' => $weekday,
            // ستون‌ها NOT NULL هستند؛ روزِ بسته با is_closed مشخص
            // می‌شود، نه با ساعتِ خالی.
            'opens_at' => $open ?? '09:00:00',
            'closes_at' => $close ?? '09:00:00',
            'is_closed' => $open === null ? 1 : 0,
        ]);
    }

    /** @return array<int,array> */
    private function strip(string $selected): array
    {
        $m = new ReflectionMethod(BookingWizardController::class, 'upcomingDays');
        $m->setAccessible(true);

        return $m->invoke(
            new BookingWizardController(),
            $this->salonId,
            30,
            new DateTimeImmutable($selected)
        );
    }

    public function test_every_day_carries_its_own_free_count(): void
    {
        // همهٔ روزها باز، ۹ تا ۱۲ → با سانس ۳۰ دقیقه‌ای، ۶ سانس
        for ($w = 0; $w <= 6; $w++) {
            $this->setHours($w, '09:00:00', '12:00:00');
        }

        $days = $this->strip((new DateTimeImmutable('tomorrow'))->format('Y-m-d'));

        self::assertNotEmpty($days);
        $tomorrow = $days[1];

        // گامِ سانس ۱۵ دقیقه است، پس خدمتِ ۳۰ دقیقه‌ای می‌تواند از
        // ۰۹:۰۰ تا ۱۱:۳۰ هر ربع شروع شود: یازده شروعِ ممکن.
        self::assertSame(11, $tomorrow['free']);
        self::assertTrue($tomorrow['available']);
    }

    public function test_a_closed_day_shows_as_full(): void
    {
        for ($w = 0; $w <= 6; $w++) {
            $this->setHours($w, '09:00:00', '12:00:00');
        }
        // روزِ پس‌فردا را ببند
        $target = new DateTimeImmutable('+2 days');
        $this->setHours(\App\Support\Jalali::weekday($target), null, null);

        $days = $this->strip((new DateTimeImmutable('today'))->format('Y-m-d'));

        self::assertSame(0, $days[2]['free']);
        self::assertFalse($days[2]['available'], 'روز بسته نباید قابل انتخاب باشد.');
    }

    public function test_the_strip_marks_exactly_one_selected_day(): void
    {
        for ($w = 0; $w <= 6; $w++) {
            $this->setHours($w, '09:00:00', '12:00:00');
        }
        $pick = (new DateTimeImmutable('+3 days'))->format('Y-m-d');

        $days = $this->strip($pick);
        $selected = array_values(array_filter($days, static fn (array $d): bool => $d['selected']));

        self::assertCount(1, $selected);
        self::assertSame($pick, $selected[0]['date']);
    }

    public function test_labels_do_not_repeat_the_day_number(): void
    {
        for ($w = 0; $w <= 6; $w++) {
            $this->setHours($w, '09:00:00', '12:00:00');
        }
        $days = $this->strip((new DateTimeImmutable('today'))->format('Y-m-d'));

        self::assertSame('امروز', $days[0]['label']);
        self::assertSame('فردا', $days[1]['label']);
        self::assertSame('پس‌فردا', $days[2]['label']);

        // از روز چهارم به بعد فقط نام روز هفته — نه «جمعه ۳ مهر»، که
        // کنار شمارهٔ بزرگِ زیرش همان عدد را دو بار می‌گفت.
        foreach (array_slice($days, 3) as $d) {
            self::assertStringNotContainsString($d['day'], $d['label']);
            self::assertDoesNotMatchRegularExpression('/\d|[۰-۹]/u', $d['label']);
        }
    }

    public function test_the_strip_covers_two_weeks(): void
    {
        for ($w = 0; $w <= 6; $w++) {
            $this->setHours($w, '09:00:00', '12:00:00');
        }
        $days = $this->strip((new DateTimeImmutable('today'))->format('Y-m-d'));

        self::assertCount(14, $days);
        self::assertSame((new DateTimeImmutable('today'))->format('Y-m-d'), $days[0]['date']);
    }
}
