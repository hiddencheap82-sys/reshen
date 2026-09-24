<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\DB;
use App\Domain\Queue\LeftoverCloser;
use PHPUnit\Framework\TestCase;

/**
 * هرچه از دیروز در صف مانده، امروز بسته می‌شود — ولی نه زودتر.
 *
 * دو شکستِ بی‌صدا که این تست جلویشان را می‌گیرد:
 *
 *   - مشتریِ «روی صندلی»ِ دیشب تا ابد می‌ماند؛ صندلی اشغال دیده می‌شد
 *     و نفر بعدیِ امروز هیچ‌وقت خودکار نمی‌نشست.
 *   - با قاعدهٔ بیش‌ازحد تند، مشتریِ ۲۳:۵۰ِ سالنی که تا بعد از
 *     نیمه‌شب باز است، ساعت ۰۰:۱۰ «غیبت» می‌خورد در حالی که نشسته.
 */
final class LeftoverCloserTest extends TestCase
{
    private int $salonId;
    private int $staffId;

    protected function setUp(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach (['payments', 'appointments', 'customers', 'staff', 'salons'] as $t) {
            DB::statement("DELETE FROM `{$t}` WHERE 1");
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->salonId = (int) DB::insert('salons', [
            'slug' => 'left-' . bin2hex(random_bytes(4)),
            'name' => 'سالن',
            'is_active' => 1,
            'seats' => 1,
        ]);
        $this->staffId = (int) DB::insert('staff', [
            'salon_id' => $this->salonId, 'name' => 'رضا', 'color' => '#2563eb', 'is_active' => 1,
        ]);
    }

    private function appt(string $status, string $kind, array $times): int
    {
        $customerId = (int) DB::insert('customers', [
            'salon_id' => $this->salonId,
            'name' => 'مشتری',
            'phone' => '+98912' . random_int(1000000, 9999999),
        ]);

        return (int) DB::insert('appointments', array_merge([
            'salon_id' => $this->salonId,
            'public_token' => bin2hex(random_bytes(6)),
            'customer_id' => $customerId,
            'staff_id' => $this->staffId,
            'kind' => $kind,
            'status' => $status,
        ], $times));
    }

    private function statusOf(int $id): string
    {
        return (string) DB::selectOne('SELECT status FROM appointments WHERE id = ?', [$id])['status'];
    }

    private static function ago(string $spec): string
    {
        return date('Y-m-d H:i:s', strtotime($spec));
    }

    // ─── صندلیِ جامانده ──────────────────────────────────────────────

    public function test_yesterdays_forgotten_chair_is_closed_as_done(): void
    {
        $id = $this->appt('in_chair', 'walkin', [
            'queued_at' => self::ago('-26 hours'),
            'actual_start_at' => self::ago('-25 hours'),
        ]);

        (new LeftoverCloser())->run();

        self::assertSame('completed', $this->statusOf($id));
    }

    /**
     * بسته‌شده باید در «تمام‌شده، تسویه‌نشده»ِ امروز بیاید، نه اینکه
     * بی‌صدا گم شود. آن فهرست با تاریخ `actual_end_at` کار می‌کند.
     */
    public function test_the_closed_chair_lands_in_todays_unpaid_list(): void
    {
        $id = $this->appt('in_chair', 'walkin', [
            'queued_at' => self::ago('-26 hours'),
            'actual_start_at' => self::ago('-25 hours'),
        ]);

        (new LeftoverCloser())->run();

        $unpaid = (new \App\Domain\Salon\SalonDashboard($this->salonId))->needsAttention(date('Y-m-d'));
        $end = DB::selectOne('SELECT actual_end_at FROM appointments WHERE id = ?', [$id])['actual_end_at'];

        self::assertSame(date('Y-m-d'), substr((string) $end, 0, 10));
        self::assertGreaterThanOrEqual(1, $unpaid['unpaid']);
    }

    public function test_todays_chair_is_left_alone(): void
    {
        $id = $this->appt('in_chair', 'walkin', [
            'queued_at' => self::ago('-40 minutes'),
            'actual_start_at' => self::ago('-20 minutes'),
        ]);

        (new LeftoverCloser())->run();

        self::assertSame('in_chair', $this->statusOf($id));
    }

    /**
     * سالنی که تا بعد از نیمه‌شب باز است: مشتری‌ای که چند دقیقه پیش
     * نشسته، هرچند تاریخش «دیروز» باشد، نباید بسته شود. شرطِ «دست‌کم
     * شش ساعت پیش» همین را تضمین می‌کند — مستقل از اینکه تست کِی اجرا
     * شود.
     */
    public function test_a_recent_chair_is_never_closed_even_across_midnight(): void
    {
        $id = $this->appt('in_chair', 'walkin', [
            'queued_at' => self::ago('-50 minutes'),
            'actual_start_at' => self::ago('-5 hours'),
        ]);

        (new LeftoverCloser())->run();

        self::assertSame('in_chair', $this->statusOf($id));
    }

    // ─── صفِ جامانده ─────────────────────────────────────────────────

    /**
     * حضوریِ دیروز که هرگز نوبتش نشد. با آستانهٔ ۲۴ ساعتهٔ قبلی، تا
     * همین ساعتِ امروز اول صف می‌نشست.
     */
    public function test_yesterdays_unserved_walkin_is_a_no_show(): void
    {
        $id = $this->appt('queued', 'walkin', ['queued_at' => self::ago('-14 hours')]);
        // «دیروز» باید واقعاً دیروز باشد، مستقل از ساعتِ اجرای تست.
        DB::statement('UPDATE appointments SET queued_at = ? WHERE id = ?', [
            date('Y-m-d 21:00:00', strtotime('-1 day')), $id,
        ]);

        (new LeftoverCloser())->run();

        self::assertSame('no_show', $this->statusOf($id));
    }

    public function test_a_booking_from_yesterday_is_a_no_show(): void
    {
        $id = $this->appt('confirmed', 'booked', [
            'scheduled_at' => date('Y-m-d 10:00:00', strtotime('-1 day')),
        ]);

        (new LeftoverCloser())->run();

        self::assertSame('no_show', $this->statusOf($id));
    }

    public function test_a_booking_later_today_is_left_alone(): void
    {
        $id = $this->appt('confirmed', 'booked', ['scheduled_at' => self::ago('+2 hours')]);

        (new LeftoverCloser())->run();

        self::assertSame('confirmed', $this->statusOf($id));
    }

    public function test_a_finished_appointment_is_never_touched(): void
    {
        $id = $this->appt('completed', 'walkin', [
            'queued_at' => self::ago('-3 days'),
            'actual_start_at' => self::ago('-3 days'),
            'actual_end_at' => self::ago('-3 days'),
        ]);
        $before = DB::selectOne('SELECT actual_end_at FROM appointments WHERE id = ?', [$id])['actual_end_at'];

        (new LeftoverCloser())->run();

        self::assertSame('completed', $this->statusOf($id));
        self::assertSame($before, DB::selectOne('SELECT actual_end_at FROM appointments WHERE id = ?', [$id])['actual_end_at']);
    }
}
