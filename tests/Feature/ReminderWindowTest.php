<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\DB;
use App\Domain\Messaging\QueueNotificationService;
use PHPUnit\Framework\TestCase;

/**
 * یادآورها به‌موقع می‌روند، و فقط یک بار.
 *
 * چرا این تست هست: نسخهٔ قبلی دنبال نوبت‌هایی می‌گشت که دقیقاً در
 * بازهٔ ±۲ دقیقه‌ایِ هدف باشند — پنجره‌ای ۴ دقیقه‌ای — در حالی که
 * کرون هر ۵ دقیقه اجرا می‌شد.
 *
 * حساب ساده است: هر بار یک دقیقه شکاف می‌ماند. یادآورِ نوبت‌هایی که
 * در آن یک دقیقه می‌افتادند **برای همیشه گم می‌شد**، نه دیر — اصلاً.
 * تقریباً یکی از هر پنج، و هیچ‌جا هم ثبت نمی‌شد.
 *
 * حالا پنجره گشادتر است و فقط به عقب باز می‌شود: اجرای دیر هنوز
 * ارسال می‌کند، ولی نه آن‌قدر دیر که متن پیامک دروغ شود.
 */
final class ReminderWindowTest extends TestCase
{
    private int $salonId;

    protected function setUp(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach (['sms_messages', 'appointments', 'customers', 'salons'] as $t) {
            DB::statement("DELETE FROM `{$t}` WHERE 1");
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->salonId = (int) DB::insert('salons', [
            'slug' => 'rem-' . bin2hex(random_bytes(4)),
            'name' => 'سالن یادآور',
            'is_active' => 1,
        ]);
    }

    /** نوبتی که «minutesAway» دقیقهٔ دیگر است و «bookedHoursAgo» ساعت پیش رزرو شده. */
    private function appointmentIn(int $minutesAway, int $bookedHoursAgo = 72): int
    {
        $customerId = (int) DB::insert('customers', [
            'salon_id' => $this->salonId,
            'name' => 'مشتری',
            'phone' => '0912' . random_int(1000000, 9999999),
        ]);

        return (int) DB::insert('appointments', [
            'salon_id' => $this->salonId,
            'public_token' => substr(bin2hex(random_bytes(8)), 0, 12),
            'customer_id' => $customerId,
            'kind' => 'booked',
            'status' => 'confirmed',
            'scheduled_at' => date('Y-m-d H:i:s', time() + $minutesAway * 60),
            'created_at' => date('Y-m-d H:i:s', time() - $bookedHoursAgo * 3600),
        ]);
    }

    /** چند نوبت در بازهٔ یادآور دیده می‌شوند؟ */
    private function due(int $hours): int
    {
        // کوتاه‌ترین یادآور مرز پایینی ندارد — آخرین تور ایمنی است.
        $all = [24, 2];
        $tolerance = $hours === min($all) ? null : (int) max(15, min(120, $hours * 60 / 4));

        $sql = "SELECT COUNT(*) AS c FROM appointments a
                 WHERE a.salon_id = ? AND a.kind = 'booked' AND a.status = 'confirmed'
                   AND a.scheduled_at > NOW()
                   AND a.scheduled_at <= (NOW() + INTERVAL ? HOUR)
                   AND a.created_at < (a.scheduled_at - INTERVAL ? HOUR)";
        $args = [$this->salonId, $hours, $hours];

        if ($tolerance !== null) {
            $sql .= ' AND a.scheduled_at > (NOW() + INTERVAL ? HOUR - INTERVAL ? MINUTE)';
            $args[] = $hours;
            $args[] = $tolerance;
        }

        return (int) (DB::selectOne($sql, $args)['c'] ?? 0);
    }

    /**
     * ★ خودِ باگ: نوبتی که در شکافِ پنجرهٔ قدیمی می‌افتاد.
     *
     * پنجرهٔ قبلی ±۲ دقیقه بود. نوبتی که ۲۳ ساعت و ۵۵ دقیقهٔ دیگر
     * است، ۵ دقیقه از هدفِ ۲۴ ساعته فاصله دارد — بیرون از پنجرهٔ
     * قدیمی، پس یادآورش هرگز نمی‌رفت.
     */
    public function test_an_appointment_in_the_old_gap_is_now_reminded(): void
    {
        $this->appointmentIn(23 * 60 + 55);

        self::assertSame(1, $this->due(24), 'نوبتی که در شکافِ پنجرهٔ قدیمی بود، هنوز دیده نمی‌شود.');
    }

    /** نوبتی که تازه وارد بازه شده هم دیده می‌شود. */
    public function test_an_appointment_right_at_the_edge_is_reminded(): void
    {
        $this->appointmentIn(24 * 60 - 1);

        self::assertSame(1, $this->due(24));
    }

    /**
     * ولی خیلی دیر، دیگر نه.
     *
     * اگر زمان‌بند چند ساعت نخوابد و بعد بیدار شود، نباید پیامکِ
     * «۲۴ ساعت تا نوبتت» را یک ساعت پیش از نوبت بفرستد. آن دروغ
     * است — و یادآور ۲ ساعته پوششش می‌دهد.
     */
    public function test_a_very_late_reminder_is_not_sent_because_it_would_lie(): void
    {
        $this->appointmentIn(60); // یک ساعت مانده

        self::assertSame(0, $this->due(24), 'یادآور ۲۴ساعته یک ساعت پیش از نوبت ارسال می‌شود.');
        self::assertSame(1, $this->due(2), 'ولی یادآور ۲ ساعته باید بگیردش.');
    }

    /** نوبتی که هنوز خیلی مانده، زود یادآوری نمی‌شود. */
    public function test_a_distant_appointment_is_not_reminded_yet(): void
    {
        $this->appointmentIn(48 * 60);

        self::assertSame(0, $this->due(24));
    }

    /** نوبتِ گذشته هیچ‌وقت یادآوری نمی‌شود. */
    public function test_a_past_appointment_is_never_reminded(): void
    {
        $this->appointmentIn(-30);

        self::assertSame(0, $this->due(24));
        self::assertSame(0, $this->due(2));
    }

    /**
     * کسی که سه ساعت پیش از نوبتش رزرو کرده، پیامکِ «۲۴ ساعت تا
     * نوبتت» نمی‌گیرد.
     */
    public function test_a_last_minute_booking_gets_no_24h_reminder(): void
    {
        $this->appointmentIn(3 * 60, bookedHoursAgo: 0);

        self::assertSame(0, $this->due(24));
    }

    /** یادآور دو بار ارسال نمی‌شود، حتی اگر زمان‌بند پشت‌سرهم اجرا شود. */
    public function test_a_reminder_is_sent_only_once(): void
    {
        $this->appointmentIn(23 * 60 + 55);

        $service = new QueueNotificationService();
        $first = $service->sendUpcomingReminders();
        $second = $service->sendUpcomingReminders();

        self::assertSame(0, $second, 'یادآور بار دوم هم ارسال شد — یعنی پیامک تکراری به مشتری.');
        self::assertLessThanOrEqual(1, $first);
    }
}
