<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\DB;
use App\Domain\Messaging\QueueNotificationService;
use PHPUnit\Framework\TestCase;

/**
 * نوبتِ رزروشده، پیش از ساعتِ خودش نیست.
 *
 * باگی که این تست قفلش می‌کند: موتور تخمین زنجیره را فقط از «الان» جلو
 * می‌برد و ساعت رزرو را نادیده می‌گرفت. نتیجه، در یک سالن واقعی:
 *
 *   - مشتری‌ای که ساعت ۱۳:۵۵ رزرو کرده بود، ساعت ۱۰:۵۵ پیامکِ «نوبت بعدی
 *     شماست، لطفاً بیایید» گرفت — سه ساعت زودتر. آن پیامک «ضروری» است
 *     و ساعت سکوت را هم رد می‌کند؛ یعنی رزروِ فردا صبح می‌توانست
 *     نیمه‌شب «بیایید» بگیرد.
 *
 *   - نوبتِ هفتهٔ بعد، تخمینش را «امروز ۱۱:۳۰» می‌گرفت. کارت نوبتِ
 *     خودِ مشتری همین تخمین را نشانش می‌داد.
 *
 * هر دو دقیقاً همان چیزی‌اند که صاحب این محصول گفت برای موفقیتش حیاتی
 * است: «تایم‌ها درست باشن و مشتری سر وقت بیاد». سیستمی که ساعت غلط
 * می‌گوید، مشتری را دیر یا زود می‌آورد.
 */
final class BookedEtaTest extends TestCase
{
    private int $salonId;
    private int $staffId;
    private int $serviceId;

    protected function setUp(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach (['sms_messages', 'appointment_items', 'appointments', 'customers',
                  'duration_stats', 'duration_samples', 'staff', 'services', 'salons'] as $t) {
            DB::statement("DELETE FROM `{$t}` WHERE 1");
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->salonId = (int) DB::insert('salons', [
            'slug' => 'eta-' . bin2hex(random_bytes(4)),
            'name' => 'سالن تخمین',
            'is_active' => 1,
            'seats' => 1,
        ]);
        $this->staffId = (int) DB::insert('staff', [
            'salon_id' => $this->salonId, 'name' => 'رضا', 'color' => '#2563eb', 'is_active' => 1,
        ]);
        $this->serviceId = (int) DB::insert('services', [
            'salon_id' => $this->salonId, 'name' => 'اصلاح مو',
            'duration_minutes' => 30, 'price' => 2500000, 'is_active' => 1,
        ]);
    }

    /**
     * @param string $when      نسبت به الان، برای strtotime
     * @param string $kind      booked یا walkin
     * @param string $status    confirmed / queued / in_chair
     */
    private function appointment(string $who, string $when, string $kind = 'booked', string $status = 'confirmed', array $extra = []): int
    {
        $customerId = (int) DB::insert('customers', [
            'salon_id' => $this->salonId,
            'name' => $who,
            'phone' => '+98912' . random_int(1000000, 9999999),
        ]);

        $at = date('Y-m-d H:i:s', strtotime($when));
        $id = (int) DB::insert('appointments', array_merge([
            'salon_id' => $this->salonId,
            'public_token' => bin2hex(random_bytes(6)),
            'customer_id' => $customerId,
            'staff_id' => $this->staffId,
            'kind' => $kind,
            'status' => $status,
            'scheduled_at' => $kind === 'booked' ? $at : null,
            'queued_at' => $kind === 'walkin' ? $at : null,
        ], $extra));

        DB::insert('appointment_items', [
            'salon_id' => $this->salonId, 'appointment_id' => $id,
            'service_id' => $this->serviceId, 'price' => 2500000, 'duration_minutes' => 30,
        ]);

        return $id;
    }

    private function sync(): void
    {
        (new QueueNotificationService())->syncStaffQueue($this->salonId, $this->staffId);
    }

    private function estimate(int $id): int
    {
        return (int) strtotime((string) DB::selectOne(
            'SELECT estimated_start_at FROM appointments WHERE id = ?',
            [$id]
        )['estimated_start_at']);
    }

    /** @return string[] کد الگوی پیامک‌هایی که برای این نوبت ثبت شده */
    private function smsFor(int $id): array
    {
        return array_column(DB::select(
            'SELECT template_code FROM sms_messages WHERE appointment_id = ? ORDER BY id',
            [$id]
        ), 'template_code');
    }

    // ─── تخمین ───────────────────────────────────────────────────────

    public function test_a_booking_later_today_is_estimated_at_its_own_time(): void
    {
        $id = $this->appointment('ساعت بعد', '+3 hours');

        $this->sync();

        $booked = strtotime((string) DB::selectOne('SELECT scheduled_at FROM appointments WHERE id = ?', [$id])['scheduled_at']);
        self::assertSame($booked, $this->estimate($id));
    }

    /**
     * همگام‌سازیِ صفِ امروز، به رزروِ هفتهٔ بعد دست نمی‌زند.
     *
     * پیش‌تر تخمینش با «امروز ۱۱:۳۰» بازنویسی می‌شد و کارت نوبتِ خودِ
     * مشتری همان را نشانش می‌داد. حالا اصلاً در صف امروز نیست؛ کارتش
     * ساعتِ رزروشده را نشان می‌دهد.
     */
    public function test_todays_sync_leaves_next_weeks_booking_alone(): void
    {
        $id = $this->appointment('هفتهٔ بعد', '+6 days');

        $this->sync();

        self::assertNull(DB::selectOne(
            'SELECT estimated_start_at FROM appointments WHERE id = ?',
            [$id]
        )['estimated_start_at']);
    }

    /**
     * صف پشتِ یک رزرو، از ساعتِ آن رزرو جلو می‌رود — نه از «الان».
     *
     * مراجعهٔ حضوری‌ای که *بعد از* ساعتِ رزرو در صف است، نمی‌تواند
     * پیش از تمام شدنِ آن رزرو شروع شود.
     */
    public function test_the_queue_behind_a_booking_starts_after_it(): void
    {
        $booked = $this->appointment('رزروی', '+2 hours');
        $walkin = $this->appointment('حضوری دیر', '+2 hours 5 minutes', 'walkin', 'queued');

        $this->sync();

        self::assertGreaterThanOrEqual($this->estimate($booked) + 30 * 60, $this->estimate($walkin));
    }

    /**
     * آرایشگر بین دو نوبت بیکار نمی‌ماند: حضوری‌ای که زودتر رسیده،
     * پیش از رزروِ دو ساعت بعد خدمت می‌گیرد — نه اینکه پشتش بماند.
     */
    public function test_a_walkin_who_arrived_first_is_not_pushed_behind_a_later_booking(): void
    {
        $this->appointment('رزروی', '+2 hours');
        $walkin = $this->appointment('حضوری', '-5 minutes', 'walkin', 'queued');

        $this->sync();

        self::assertLessThan(strtotime('+1 hour'), $this->estimate($walkin));
    }

    // ─── پیامک «بیایید» ──────────────────────────────────────────────

    public function test_a_booked_customer_is_not_told_to_come_hours_early(): void
    {
        $id = $this->appointment('ساعت بعد', '+3 hours');

        $this->sync();

        self::assertNotContains('queue_chair_ready', $this->smsFor($id));
    }

    public function test_a_booking_next_week_gets_no_queue_sms_at_all(): void
    {
        $id = $this->appointment('هفتهٔ بعد', '+6 days');

        $this->sync();

        self::assertSame([], $this->smsFor($id));
    }

    /** نزدیکِ ساعتش و اول صف: حالا «بیایید» درست است. */
    public function test_a_booked_customer_near_their_time_is_told_to_come(): void
    {
        $id = $this->appointment('نزدیک', '+10 minutes');

        $this->sync();

        self::assertContains('queue_chair_ready', $this->smsFor($id));
    }

    /**
     * مراجعهٔ حضوری همان رفتار قبلی را دارد: در سالن نشسته، و اول صف
     * بودن یعنی «نوبت بعدی شماست».
     */
    public function test_a_walkin_at_the_front_still_gets_chair_ready(): void
    {
        $id = $this->appointment('حضوری', '-5 minutes', 'walkin', 'queued');

        $this->sync();

        self::assertContains('queue_chair_ready', $this->smsFor($id));
    }

    // ─── صفِ امروز ───────────────────────────────────────────────────

    /**
     * «صف زنده» فقط امروز است.
     *
     * پیش‌تر رزروهای روزهای بعد پشتِ صف امروز می‌آمدند، با ساعت‌هایی
     * مثل ۰۰:۲۰ بامداد — چیزی که در اولین عکسِ صفحهٔ صف دیده شد.
     */
    public function test_the_live_queue_holds_only_todays_bookings(): void
    {
        $today = $this->appointment('امروز', '+1 hour');
        $this->appointment('فردا', '+1 day');
        $this->appointment('هفتهٔ بعد', '+6 days');

        $ids = array_map('intval', array_column(
            (new \App\Domain\Appointment\AppointmentRepository())->activeForStaff($this->salonId, $this->staffId),
            'id'
        ));

        self::assertSame([$today], $ids);
    }

    /**
     * رزروِ دیروزی که هرگز بسته نشد، نباید برای همیشه اول صف بنشیند و
     * تخمینِ همه را عقب ببرد.
     */
    public function test_an_unresolved_booking_from_yesterday_is_not_in_todays_queue(): void
    {
        $stale = $this->appointment('دیروز', '-1 day');
        $walkin = $this->appointment('حضوری', '-5 minutes', 'walkin', 'queued');

        $ids = array_map('intval', array_column(
            (new \App\Domain\Appointment\AppointmentRepository())->activeForStaff($this->salonId, $this->staffId),
            'id'
        ));

        self::assertNotContains($stale, $ids);
        self::assertContains($walkin, $ids);
    }

    /** کسی که همین حالا در صف یا روی صندلی است، از هر روزی، می‌ماند. */
    public function test_someone_already_in_the_chair_stays_whatever_the_date(): void
    {
        $id = $this->appointment('روی صندلی', '-1 day', 'walkin', 'in_chair', [
            'actual_start_at' => date('Y-m-d H:i:s', strtotime('-10 minutes')),
        ]);

        $ids = array_map('intval', array_column(
            (new \App\Domain\Appointment\AppointmentRepository())->activeForStaff($this->salonId, $this->staffId),
            'id'
        ));

        self::assertContains($id, $ids);
    }

    // ─── متنِ کارت نوبت ──────────────────────────────────────────────

    public function test_first_in_line_hours_early_is_not_told_its_next(): void
    {
        $engine = new \App\Domain\Queue\EtaEngine();
        $now = new \DateTimeImmutable('2026-09-24 15:00:00');
        $start = new \DateTimeImmutable('2026-09-24 18:00:00');

        $text = $engine->displayText(0, $start, $start, $now)['text'];

        self::assertNotSame('نوبت بعدی توست', $text);
        self::assertStringContainsString('۱۸:۰۰', $text);
    }

    public function test_first_in_line_right_now_is_told_its_next(): void
    {
        $engine = new \App\Domain\Queue\EtaEngine();
        $now = new \DateTimeImmutable('2026-09-24 15:00:00');

        self::assertSame('نوبت بعدی توست', $engine->displayText(0, $now, $now, $now)['text']);
    }

    /** ساعتِ بی‌تاریخ یعنی «امروز» — بعد از نیمه‌شب، تاریخ لازم است. */
    public function test_a_time_on_another_day_carries_the_day(): void
    {
        $engine = new \App\Domain\Queue\EtaEngine();
        $now = new \DateTimeImmutable('2026-09-24 22:30:00');
        $start = new \DateTimeImmutable('2026-09-25 00:20:00');

        $text = $engine->displayText(3, $start, $start->modify('+15 minutes'), $now)['text'];

        self::assertStringContainsString('فردا', $text);
    }

    /** «۱۶:۰۰ تا ۱۶:۰۰» درست است ولی آدم را مکث می‌اندازد. */
    public function test_a_zero_width_window_reads_as_one_time(): void
    {
        $engine = new \App\Domain\Queue\EtaEngine();
        $now = new \DateTimeImmutable('2026-09-24 11:00:00');
        $start = new \DateTimeImmutable('2026-09-24 16:00:00');

        self::assertSame('ساعت ۱۶:۰۰', $engine->displayText(3, $start, $start, $now)['text']);
    }

    public function test_a_time_today_carries_no_day(): void
    {
        $engine = new \App\Domain\Queue\EtaEngine();
        $now = new \DateTimeImmutable('2026-09-24 15:00:00');
        $start = new \DateTimeImmutable('2026-09-24 17:00:00');

        $text = $engine->displayText(3, $start, $start->modify('+15 minutes'), $now)['text'];

        self::assertStringStartsWith('حدود ساعت', $text);
    }

    // ─── پیامک «عقبیم» ───────────────────────────────────────────────

    /**
     * تخمینی که پیش از این اصلاح به‌غلط «الان» ذخیره شده بود، نباید
     * بار اول یک «عقبیم»ِ دروغ بسازد. مشتری درست سر وقتش است.
     */
    public function test_a_stale_wrong_estimate_does_not_cause_a_false_delay_sms(): void
    {
        $id = $this->appointment('ساعت بعد', '+3 hours', 'booked', 'confirmed', [
            'estimated_start_at' => date('Y-m-d H:i:s'),
        ]);

        $this->sync();

        self::assertNotContains('queue_delayed', $this->smsFor($id));
    }

    /** ولی تأخیرِ واقعی — دیرتر از ساعتِ رزرو — باید گفته شود. */
    public function test_a_real_delay_past_the_booked_time_is_reported(): void
    {
        // سه حضوریِ جلوتر که از قبل در صف‌اند، رزروِ ۲۰ دقیقهٔ بعد را
        // دست‌کم یک ساعت عقب می‌اندازند.
        $this->appointment('حضوری ۱', '-30 minutes', 'walkin', 'in_chair', [
            'actual_start_at' => date('Y-m-d H:i:s', strtotime('-1 minute')),
        ]);
        $this->appointment('حضوری ۲', '-25 minutes', 'walkin', 'queued');
        $this->appointment('حضوری ۳', '-20 minutes', 'walkin', 'queued');
        $id = $this->appointment('رزروی', '+20 minutes');

        $this->sync();

        self::assertContains('queue_delayed', $this->smsFor($id));
    }
}
