<?php

declare(strict_types=1);

namespace App\Domain\Booking;

use App\Core\Auth;
use App\Core\DB;

/**
 * «چند نوبت تازه آمده که هنوز ندیده‌ام؟»
 *
 * مشکلی که حل می‌کند: صفحهٔ «صف زنده» فقط امروز را نشان می‌دهد. مشتری
 * که از اینترنت برای سه‌شنبهٔ بعد نوبت می‌گیرد، در هیچ صفحه‌ای جلوی
 * چشم آرایشگر نمی‌آید مگر اینکه خودش برود «رزروها» را باز کند. برای
 * کسب‌وکاری که تازه از دفترچه آمده، سیستمی که ساکت است یعنی سیستمی که
 * قابل اعتماد نیست.
 *
 * «دیده‌ام» برای هر کاربر جداست، نه برای سالن: صاحب سالن ممکن است
 * صبح دیده باشد و پذیرش هنوز نه. نشانی که بگوید «کسی دیگر دیده، پس
 * تو هم دیده‌ای» دروغ است.
 *
 * فقط رزروِ آینده شمرده می‌شود. نوبتی که وقتش گذشته دیگر خبر نیست؛
 * اگر بشمریمش، نشانِ قرمزی می‌ماند که هیچ کاری نمی‌شود برایش کرد.
 */
final class NewBookings
{
    /**
     * چند رزروِ آینده از آخرین باری که این کاربر «رزروها» را باز
     * کرده، ثبت شده است.
     *
     * کاربری که هیچ‌وقت ندیده، از *روزِ عضویتش* شمرده می‌شود — نه از
     * همیشه، نه از هیچ‌وقت.
     *
     * «از همیشه» یعنی پذیرشِ تازهٔ یک سالنِ پرکار، اولین روز با نشانِ
     * «۴۷ نوبت تازه» روبه‌رو شود که هیچ معنایی ندارد. «از هیچ‌وقت» —
     * رفتارِ قبلی — یعنی صاحبِ سالنِ *تازه* اولین رزروهای اینترنتی‌اش را
     * نبیند، چون هنوز یک بار هم «رزروها» را باز نکرده بود؛ همان
     * رزروهایی که بیش از همه منتظرشان است. نصبِ تازه همین را نشان داد.
     */
    public static function countFor(int $salonId, int $userId): int
    {
        $row = DB::selectOne(
            'SELECT COALESCE(bookings_seen_at, created_at) AS seen
               FROM salon_user WHERE salon_id = ? AND user_id = ?',
            [$salonId, $userId]
        );

        if ($row === null || $row['seen'] === null) {
            return 0;
        }

        $count = DB::selectOne(
            "SELECT COUNT(*) AS c
               FROM appointments
              WHERE salon_id = ?
                AND kind = 'booked'
                AND status = 'confirmed'
                AND scheduled_at > NOW()
                AND created_at > ?",
            [$salonId, $row['seen']]
        );

        return (int) ($count['c'] ?? 0);
    }

    /** نشانِ نوار پنل برای کاربرِ جاری. */
    public static function forCurrentUser(): int
    {
        $salonId = Auth::salonId();
        $userId = Auth::id();

        if ($salonId === null || $userId === null) {
            return 0;
        }

        return self::countFor($salonId, $userId);
    }

    /**
     * «دیدم.»
     *
     * صفحهٔ رزروها این را صدا می‌زند. عمداً *بعد از* خواندنِ فهرست
     * اجرا می‌شود تا همان بازدیدی که نشان را می‌بیند، خودش پاکش کند —
     * نه بازدید بعدی.
     */
    public static function markSeen(int $salonId, int $userId): void
    {
        DB::statement(
            'UPDATE salon_user SET bookings_seen_at = NOW() WHERE salon_id = ? AND user_id = ?',
            [$salonId, $userId]
        );
    }

    /**
     * رزروهای تازه، برای نشان دادن روی داشبورد.
     *
     * @return array<int,array>
     */
    public static function recent(int $salonId, int $limit = 5): array
    {
        return DB::select(
            "SELECT a.id, a.public_token, a.scheduled_at, a.created_at,
                    c.name AS customer_name, c.phone AS customer_phone,
                    st.name AS staff_name
               FROM appointments a
               LEFT JOIN customers c ON c.id = a.customer_id
               LEFT JOIN staff st ON st.id = a.staff_id
              WHERE a.salon_id = ?
                AND a.kind = 'booked'
                AND a.status = 'confirmed'
                AND a.scheduled_at > NOW()
                AND a.created_at >= DATE_SUB(NOW(), INTERVAL 2 DAY)
              ORDER BY a.created_at DESC
              LIMIT " . max(1, min(20, $limit)),
            [$salonId]
        );
    }
}
