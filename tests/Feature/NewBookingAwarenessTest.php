<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\DB;
use App\Domain\Booking\NewBookings;
use PHPUnit\Framework\TestCase;

/**
 * «آرایشگر بفهمد نوبت تازه آمده.»
 *
 * چرا این تست هست: نبودنِ این نشان، شکستی بود که هیچ خطایی نداشت.
 * مشتری از اینترنت برای سه‌شنبهٔ بعد نوبت می‌گرفت، پیامک تأییدش را
 * هم می‌گرفت، و سالن خبر نداشت — چون «صف زنده» فقط امروز را نشان
 * می‌دهد. مشتری سر وقت می‌رسید به سالنی که منتظرش نبود.
 *
 * سه چیز اینجا می‌تواند بی‌صدا بشکند و هر سه را می‌سنجیم: شمردنِ
 * درست، پاک شدنِ نشان با دیدن، و جدا بودنِ «دیدم» برای هر کاربر.
 */
final class NewBookingAwarenessTest extends TestCase
{
    private int $salonId;
    private int $ownerId;
    private int $receptionId;
    private int $customerId;

    protected function setUp(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach (['appointments', 'customers', 'salon_user', 'users', 'salons'] as $t) {
            DB::statement("DELETE FROM `{$t}` WHERE 1");
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->salonId = (int) DB::insert('salons', [
            'slug' => 'new-' . bin2hex(random_bytes(4)),
            'name' => 'سالن نشان',
            'is_active' => 1,
            'plan_code' => 'trial',
            'seats' => 1,
        ]);

        $this->ownerId = $this->addMember('owner');
        $this->receptionId = $this->addMember('reception');

        $this->customerId = (int) DB::insert('customers', [
            'salon_id' => $this->salonId,
            'name' => 'مهدی',
            'phone' => '+989120000001',
        ]);
    }

    private function addMember(string $role): int
    {
        $userId = (int) DB::insert('users', [
            'phone' => '+98912' . random_int(1000000, 9999999),
        ]);
        DB::insert('salon_user', [
            'salon_id' => $this->salonId,
            'user_id' => $userId,
            'role' => $role,
            'is_active' => 1,
        ]);

        return $userId;
    }

    /** @param string $when مقدارِ strtotime برای scheduled_at */
    private function book(string $when = '+3 days', string $status = 'confirmed', string $kind = 'booked'): int
    {
        return (int) DB::insert('appointments', [
            'salon_id' => $this->salonId,
            'public_token' => bin2hex(random_bytes(6)),
            'customer_id' => $this->customerId,
            'kind' => $kind,
            'status' => $status,
            'scheduled_at' => date('Y-m-d H:i:s', strtotime($when)),
        ]);
    }

    // ─── شمارش ───────────────────────────────────────────────────────

    /**
     * رزروهای پیش از عضویت، برای عضوِ تازه «تازه» نیستند.
     *
     * اگر «همه» شمرده می‌شد، اولین ورودِ پذیرشِ تازهٔ یک سالنِ پرکار با
     * نشانِ «۴۷ نوبت تازه» روبه‌رو می‌شد که هیچ معنایی ندارد و از همان
     * روز اول نادیده گرفته می‌شود.
     */
    public function test_bookings_from_before_joining_are_not_new(): void
    {
        $id = $this->book();
        DB::update('appointments', ['created_at' => date('Y-m-d H:i:s', strtotime('-2 days'))], 'id = :id', ['id' => $id]);

        self::assertSame(0, NewBookings::countFor($this->salonId, $this->ownerId));
    }

    /**
     * صاحبِ سالنِ تازه، اولین رزروش را می‌بیند — حتی اگر هنوز یک بار هم
     * «رزروها» را باز نکرده باشد.
     *
     * پیش‌تر «هیچ‌وقت ندیده» یعنی صفر، و همین صاحبِ سالنی را که تازه
     * راه افتاده بود از اولین رزروهای اینترنتی‌اش بی‌خبر می‌گذاشت.
     */
    public function test_a_new_owner_sees_their_first_booking(): void
    {
        DB::statement(
            'UPDATE salon_user SET created_at = ? WHERE salon_id = ? AND user_id = ?',
            [date('Y-m-d H:i:s', strtotime('-1 hour')), $this->salonId, $this->ownerId]
        );

        $this->book();

        self::assertSame(1, NewBookings::countFor($this->salonId, $this->ownerId));
    }

    public function test_bookings_made_after_the_last_look_are_counted(): void
    {
        NewBookings::markSeen($this->salonId, $this->ownerId);
        $this->backdateSeen($this->ownerId, '-1 hour');

        $this->book();
        $this->book('+5 days');

        self::assertSame(2, NewBookings::countFor($this->salonId, $this->ownerId));
    }

    public function test_opening_the_page_clears_the_badge(): void
    {
        NewBookings::markSeen($this->salonId, $this->ownerId);
        $this->backdateSeen($this->ownerId, '-1 hour');
        $this->book();
        self::assertSame(1, NewBookings::countFor($this->salonId, $this->ownerId));

        NewBookings::markSeen($this->salonId, $this->ownerId);

        self::assertSame(0, NewBookings::countFor($this->salonId, $this->ownerId));
    }

    /**
     * «دیدم» برای هر کاربر جداست.
     *
     * نشانی که بگوید «صاحب سالن دیده، پس پذیرش هم دیده» دروغ است — و
     * دقیقاً همان نفری را که باید خبردار شود، بی‌خبر می‌گذارد.
     */
    public function test_seen_is_per_user_not_per_salon(): void
    {
        foreach ([$this->ownerId, $this->receptionId] as $userId) {
            NewBookings::markSeen($this->salonId, $userId);
            $this->backdateSeen($userId, '-1 hour');
        }

        $this->book();
        NewBookings::markSeen($this->salonId, $this->ownerId);

        self::assertSame(0, NewBookings::countFor($this->salonId, $this->ownerId));
        self::assertSame(1, NewBookings::countFor($this->salonId, $this->receptionId));
    }

    // ─── چه چیزی شمرده نمی‌شود ───────────────────────────────────────

    /** نوبتی که وقتش گذشته خبر نیست؛ نشانِ قرمزی است که کاری نمی‌شود کرد. */
    public function test_a_booking_in_the_past_is_not_counted(): void
    {
        NewBookings::markSeen($this->salonId, $this->ownerId);
        $this->backdateSeen($this->ownerId, '-1 hour');

        $this->book('-2 days');

        self::assertSame(0, NewBookings::countFor($this->salonId, $this->ownerId));
    }

    /** مراجعهٔ حضوری خبر نیست: آرایشگر همان لحظه آنجا بوده. */
    public function test_a_walkin_is_not_counted(): void
    {
        NewBookings::markSeen($this->salonId, $this->ownerId);
        $this->backdateSeen($this->ownerId, '-1 hour');

        $this->book('+2 hours', 'queued', 'walkin');

        self::assertSame(0, NewBookings::countFor($this->salonId, $this->ownerId));
    }

    public function test_a_cancelled_booking_is_not_counted(): void
    {
        NewBookings::markSeen($this->salonId, $this->ownerId);
        $this->backdateSeen($this->ownerId, '-1 hour');

        $this->book('+3 days', 'cancelled');

        self::assertSame(0, NewBookings::countFor($this->salonId, $this->ownerId));
    }

    // ─── فهرست ───────────────────────────────────────────────────────

    public function test_recent_returns_the_newest_first(): void
    {
        $old = $this->book('+9 days');
        DB::statement(
            'UPDATE appointments SET created_at = ? WHERE id = ?',
            [date('Y-m-d H:i:s', strtotime('-1 day')), $old]
        );
        $new = $this->book('+2 days');

        $ids = array_map('intval', array_column(NewBookings::recent($this->salonId), 'id'));

        self::assertSame([$new, $old], $ids);
    }

    /**
     * `bookings_seen_at` را عقب می‌برد.
     *
     * لازم است چون `markSeen` روی NOW() می‌نشیند و نوبتی که در همان
     * ثانیه ساخته می‌شود «تازه‌تر» شمرده نمی‌شود. در واقعیت این فاصله
     * همیشه هست؛ در تست باید ساختگی درستش کرد.
     */
    private function backdateSeen(int $userId, string $when): void
    {
        DB::statement(
            'UPDATE salon_user SET bookings_seen_at = ? WHERE salon_id = ? AND user_id = ?',
            [date('Y-m-d H:i:s', strtotime($when)), $this->salonId, $userId]
        );
    }
}
