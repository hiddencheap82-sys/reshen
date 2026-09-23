<?php

declare(strict_types=1);

namespace App\Domain\Platform;

use App\Core\Auth;
use App\Core\DB;

/**
 * رد پای کارهای مدیریتی.
 *
 * تا حالا فقط «ورود به‌جای صاحب سالن» ثبت می‌شد، و همان یک مورد هم
 * مستقیم داخل کنترلر نوشته شده بود. ولی عوض کردن پلن یک سالن، بستن
 * سالن، یا پرداخت‌شده کردن یک صورتحساب هم همان‌قدر مهم‌اند: همه با
 * پول یا دسترسی سروکار دارند.
 *
 * قاعده‌ای که اینجا برقرار است: **هر کاری که از پنل پلتفرم روی دادهٔ
 * یک سالن اثر بگذارد، رد می‌گذارد.** دسترسی‌ای که رد نگذارد،
 * دسترسی‌ای است که کسی جوابگویش نیست — و پنل پلتفرم بالاترین دسترسیِ
 * این سیستم است.
 *
 * ثبت عمداً هیچ‌وقت کار اصلی را نمی‌شکند: اگر نوشتن لاگ خطا بدهد،
 * تغییر انجام می‌شود. لاگِ ازدست‌رفته بد است، ولی سالنی که نمی‌تواند
 * پلنش عوض شود بدتر است.
 */
final class AuditLog
{
    public const SUPPORT_LOGIN = 'support_login_as';
    public const PLAN_CHANGED = 'plan_changed';
    public const SALON_ACTIVATED = 'salon_activated';
    public const SALON_DEACTIVATED = 'salon_deactivated';
    public const INVOICE_ISSUED = 'invoice_issued';
    public const INVOICE_PAID = 'invoice_paid';
    public const INVOICE_CANCELLED = 'invoice_cancelled';
    public const PLATFORM_ADMIN_GRANTED = 'platform_admin_granted';
    public const PLATFORM_ADMIN_REVOKED = 'platform_admin_revoked';
    public const USER_CREATED = 'user_created';
    public const USER_PASSWORD_RESET = 'user_password_reset';
    public const SALON_CREATED = 'salon_created';
    public const SUPPORT_REPLIED = 'support_replied';
    public const SUPPORT_CLOSED = 'support_closed';

    /** عنوان فارسیِ هر کنش، برای صفحهٔ گزارش. */
    private const LABELS = [
        self::SUPPORT_LOGIN => 'ورود پشتیبانی به‌جای سالن',
        self::PLAN_CHANGED => 'تغییر پلن',
        self::SALON_ACTIVATED => 'فعال کردن سالن',
        self::SALON_DEACTIVATED => 'غیرفعال کردن سالن',
        self::INVOICE_ISSUED => 'صدور صورتحساب',
        self::INVOICE_PAID => 'پرداخت صورتحساب',
        self::INVOICE_CANCELLED => 'لغو صورتحساب',
        self::PLATFORM_ADMIN_GRANTED => 'دادن دسترسی مدیر پلتفرم',
        self::PLATFORM_ADMIN_REVOKED => 'گرفتن دسترسی مدیر پلتفرم',
        self::USER_CREATED => 'ساخت کاربر',
        self::USER_PASSWORD_RESET => 'بازنشانی رمز کاربر',
        self::SALON_CREATED => 'ساخت سالن',
        self::SUPPORT_REPLIED => 'جواب پشتیبانی',
        self::SUPPORT_CLOSED => 'بستن تیکت پشتیبانی',
    ];

    /**
     * @param array<string,mixed> $meta جزئیاتی که بعداً لازم می‌شود
     */
    public static function record(
        string $action,
        ?int $salonId = null,
        ?string $subjectType = null,
        ?int $subjectId = null,
        array $meta = []
    ): void {
        try {
            DB::insert('audit_logs', [
                'salon_id' => $salonId,
                'actor_user_id' => Auth::id(),
                'action' => $action,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'meta_json' => $meta === [] ? null : json_encode($meta, JSON_UNESCAPED_UNICODE),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            ]);
        } catch (\Throwable $e) {
            // عمداً بلعیده می‌شود — بالای کلاس توضیح داده شده.
            error_log('audit log failed: ' . $e->getMessage());
        }
    }

    public static function label(string $action): string
    {
        return self::LABELS[$action] ?? $action;
    }

    /**
     * آخرین کنش‌ها، با نام کنشگر و سالن.
     *
     * @return array<int,array>
     */
    public static function recent(int $limit = 50, ?int $salonId = null): array
    {
        $sql = 'SELECT al.*, u.phone AS actor_phone, u.name AS actor_name, s.name AS salon_name
                  FROM audit_logs al
                  LEFT JOIN users u ON u.id = al.actor_user_id
                  LEFT JOIN salons s ON s.id = al.salon_id';
        $args = [];

        if ($salonId !== null) {
            $sql .= ' WHERE al.salon_id = ?';
            $args[] = $salonId;
        }

        $sql .= ' ORDER BY al.id DESC LIMIT ' . max(1, min(500, $limit));

        return DB::select($sql, $args);
    }
}
