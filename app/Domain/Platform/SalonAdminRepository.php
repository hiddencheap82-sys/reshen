<?php

declare(strict_types=1);

namespace App\Domain\Platform;

use App\Core\DB;

/**
 * سالن‌ها از دید پلتفرم.
 *
 * فرقش با مخزن‌های دیگر این است که عمداً به `salon_id` محدود نمی‌شود —
 * تنها جایی در برنامه که چنین چیزی مجاز است. به همین دلیل هر متدِ
 * اینجا فقط از پشت `PlatformAdminRequired` صدا زده می‌شود، و تست
 * دسترسی همین را قفل کرده.
 */
final class SalonAdminRepository
{
    /**
     * فهرست سالن‌ها با آماری که تصمیم‌سازند.
     *
     * «آخرین فعالیت» مهم‌ترین ستون است: سالنی که دو هفته هیچ نوبتی ثبت
     * نکرده، دارد می‌رود — و این تنها چیزی است که پیش از لغو اشتراک
     * می‌شود دید.
     *
     * @param string $search نام یا نشانی سالن
     * @return array<int,array>
     */
    public function all(string $search = '', string $status = ''): array
    {
        $where = [];
        $args = [];

        if (trim($search) !== '') {
            $where[] = '(s.name LIKE ? OR s.slug LIKE ? OR s.city LIKE ?)';
            $like = '%' . trim($search) . '%';
            $args[] = $like;
            $args[] = $like;
            $args[] = $like;
        }
        if ($status === 'active') {
            $where[] = 's.is_active = 1';
        } elseif ($status === 'inactive') {
            $where[] = 's.is_active = 0';
        }

        $sql = "SELECT s.*,
                    (SELECT COUNT(*) FROM staff st
                      WHERE st.salon_id = s.id AND st.is_active = 1) AS staff_count,
                    (SELECT COUNT(*) FROM appointments a
                      WHERE a.salon_id = s.id AND a.status = 'completed') AS completed_count,
                    (SELECT COUNT(*) FROM appointments a
                      WHERE a.salon_id = s.id
                        AND a.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) AS bookings_30d,
                    (SELECT MAX(a.created_at) FROM appointments a
                      WHERE a.salon_id = s.id) AS last_booking_at,
                    (SELECT COUNT(*) FROM customers c
                      WHERE c.salon_id = s.id) AS customer_count
                FROM salons s";

        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY s.created_at DESC';

        return DB::select($sql, $args);
    }

    public function find(int $salonId): ?array
    {
        return DB::selectOne('SELECT * FROM salons WHERE id = ?', [$salonId]);
    }

    /**
     * پلن، تعداد صندلی و پایان دورهٔ آزمایش.
     *
     * `trial_ends_at` تهی یعنی «بدون مهلت» — برای سالنی که پلن پولی
     * دارد درست است، ولی برای سالنِ آزمایشی یعنی آزمایشِ بی‌پایان.
     * صفحهٔ سالن این حالت را جدا نشان می‌دهد.
     */
    public function updatePlan(int $salonId, string $planCode, int $seats, ?string $trialEndsAt): void
    {
        DB::update('salons', [
            'plan_code' => $planCode,
            'seats' => max(1, $seats),
            'trial_ends_at' => $trialEndsAt,
        ], 'id = :id', ['id' => $salonId]);
    }

    /**
     * فعال یا غیرفعال کردن سالن.
     *
     * غیرفعال یعنی صفحهٔ عمومی‌اش بسته می‌شود و مشتری نمی‌تواند نوبت
     * بگیرد — ولی داده‌اش دست‌نخورده می‌ماند. حذف نمی‌کنیم: سالنی که
     * اشتراکش تمام شده ممکن است ماه بعد برگردد، و رفتنِ پروندهٔ
     * مشتری‌هایش یعنی دیگر برنمی‌گردد.
     */
    public function setActive(int $salonId, bool $active): void
    {
        DB::update('salons', ['is_active' => $active ? 1 : 0], 'id = :id', ['id' => $salonId]);
    }

    /** شمارهٔ کلی پلتفرم برای صفحهٔ نخست. */
    public function metrics(): array
    {
        $row = DB::selectOne(
            "SELECT
                (SELECT COUNT(*) FROM salons WHERE is_active = 1) AS active_salons,
                (SELECT COUNT(*) FROM salons WHERE is_active = 0) AS inactive_salons,
                (SELECT COUNT(*) FROM salons
                  WHERE plan_code = 'trial' AND is_active = 1) AS trial_salons,
                (SELECT COUNT(*) FROM users) AS users,
                (SELECT COUNT(*) FROM appointments
                  WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) AS bookings_7d,
                (SELECT COUNT(*) FROM appointments
                  WHERE status = 'completed'
                    AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) AS completed_7d,
                (SELECT COUNT(*) FROM sms_messages
                  WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) AS sms_30d,
                (SELECT COUNT(*) FROM sms_messages
                  WHERE status = 'failed'
                    AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) AS sms_failed_30d"
        );

        return $row ?? [];
    }

    /**
     * سالن‌هایی که دارند می‌روند.
     *
     * تعریف: فعال است، ولی دو هفته است هیچ نوبتی ثبت نکرده. این تنها
     * هشداری است که پیش از لغو اشتراک به دست می‌آید — بعدش دیگر دیر
     * است.
     *
     * @return array<int,array>
     */
    public function goingQuiet(int $days = 14): array
    {
        return DB::select(
            "SELECT s.id, s.name, s.slug, s.plan_code,
                    (SELECT MAX(a.created_at) FROM appointments a WHERE a.salon_id = s.id) AS last_booking_at
               FROM salons s
              WHERE s.is_active = 1
                AND NOT EXISTS (
                    SELECT 1 FROM appointments a
                     WHERE a.salon_id = s.id
                       AND a.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                )
              ORDER BY last_booking_at IS NULL, last_booking_at ASC",
            [$days]
        );
    }

    /**
     * مصرف پیامک هر سالن در ۳۰ روز گذشته.
     *
     * پیامک تنها هزینهٔ متغیر ماست، پس سالنِ پرمصرف باید دیده شود —
     * هم برای صورتحساب، هم برای اینکه بفهمیم کجا چیزی درست کار
     * نمی‌کند (مثلاً یادآوری که دوبار می‌رود).
     *
     * @return array<int,array>
     */
    public function smsUsage(int $days = 30): array
    {
        return DB::select(
            "SELECT s.id, s.name,
                    COUNT(m.id) AS total,
                    SUM(m.status = 'sent') AS sent,
                    SUM(m.status = 'failed') AS failed
               FROM salons s
               LEFT JOIN sms_messages m
                      ON m.salon_id = s.id
                     AND m.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
              GROUP BY s.id, s.name
              HAVING total > 0
              ORDER BY total DESC",
            [$days]
        );
    }
}
