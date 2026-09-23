<?php

declare(strict_types=1);

namespace App\Domain\Support;

use App\Core\DB;

/**
 * تیکت پشتیبانی — دو طرف، یک رشته پیام.
 *
 * وضعیت‌ها عمداً سه‌تا و معنی‌شان «توپ زمین کیست» است، نه «چقدر مهم
 * است»:
 *
 *   open     — سالن حرف زده، منتظر ماست
 *   answered — ما جواب داده‌ایم، منتظر سالن
 *   closed   — تمام
 *
 * به همین دلیل جواب دادن و جواب گرفتن خودشان وضعیت را عوض می‌کنند و
 * کسی لازم نیست دستی چیزی را «در حال بررسی» بگذارد. شمارندهٔ صفحهٔ
 * نخستِ پلتفرم هم همین `open` را می‌شمارد: یعنی «چند نفر منتظر
 * جوابند».
 *
 * تنانسی: هر متدی که از سمت سالن صدا زده می‌شود `salon_id` می‌گیرد و
 * در `WHERE` می‌گذاردش. متدهای سمت پلتفرم این محدودیت را ندارند —
 * همان‌طور که بقیهٔ `Domain/Platform` ندارند — ولی فقط از پشت
 * `PlatformAdminRequired` صدا زده می‌شوند.
 */
final class TicketRepository
{
    public const OPEN = 'open';
    public const ANSWERED = 'answered';
    public const CLOSED = 'closed';

    public const SIDE_SALON = 'salon';
    public const SIDE_PLATFORM = 'platform';

    /** تیکت تازه، با اولین پیامش. شناسهٔ تیکت برمی‌گردد. */
    public function open(int $salonId, ?int $userId, string $subject, string $body): int
    {
        return (int) DB::transaction(function () use ($salonId, $userId, $subject, $body) {
            $id = (int) DB::insert('support_tickets', [
                'salon_id' => $salonId,
                'opened_by_user_id' => $userId,
                'subject' => mb_substr(trim($subject), 0, 150),
                'status' => self::OPEN,
                'last_message_at' => date('Y-m-d H:i:s'),
            ]);

            DB::insert('support_messages', [
                'ticket_id' => $id,
                'user_id' => $userId,
                'side' => self::SIDE_SALON,
                'body' => trim($body),
            ]);

            return $id;
        });
    }

    /**
     * پیام تازه روی تیکت.
     *
     * وضعیت خودش جابه‌جا می‌شود: جوابِ ما یعنی «منتظر سالن»، حرفِ
     * سالن یعنی «منتظر ما» — حتی اگر تیکت بسته بوده باشد. بسته‌ماندنِ
     * تیکتی که طرف دوباره حرف زده، یعنی گم شدنِ حرفش.
     */
    public function reply(int $ticketId, ?int $userId, string $side, string $body): void
    {
        DB::transaction(function () use ($ticketId, $userId, $side, $body) {
            DB::insert('support_messages', [
                'ticket_id' => $ticketId,
                'user_id' => $userId,
                'side' => $side,
                'body' => trim($body),
            ]);

            DB::update('support_tickets', [
                'status' => $side === self::SIDE_PLATFORM ? self::ANSWERED : self::OPEN,
                'last_message_at' => date('Y-m-d H:i:s'),
                'closed_at' => null,
            ], 'id = :id', ['id' => $ticketId]);
        });
    }

    public function close(int $ticketId): void
    {
        DB::update('support_tickets', [
            'status' => self::CLOSED,
            'closed_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $ticketId]);
    }

    public function find(int $ticketId, ?int $salonId = null): ?array
    {
        $sql = 'SELECT t.*, s.name AS salon_name, s.slug AS salon_slug
                  FROM support_tickets t
                  JOIN salons s ON s.id = t.salon_id
                 WHERE t.id = ?';
        $args = [$ticketId];

        // سمت سالن: تیکت سالن دیگری حتی با شناسهٔ درست هم پیدا نمی‌شود.
        if ($salonId !== null) {
            $sql .= ' AND t.salon_id = ?';
            $args[] = $salonId;
        }

        return DB::selectOne($sql, $args);
    }

    /** @return array<int,array> */
    public function messages(int $ticketId): array
    {
        return DB::select(
            'SELECT m.*, u.name AS user_name
               FROM support_messages m
               LEFT JOIN users u ON u.id = m.user_id
              WHERE m.ticket_id = ?
              ORDER BY m.id ASC',
            [$ticketId]
        );
    }

    /**
     * تیکت‌های یک سالن.
     *
     * @return array<int,array>
     */
    public function forSalon(int $salonId): array
    {
        return DB::select(
            'SELECT t.*,
                    (SELECT COUNT(*) FROM support_messages m WHERE m.ticket_id = t.id) AS message_count
               FROM support_tickets t
              WHERE t.salon_id = ?
              ORDER BY t.status = ? DESC, t.last_message_at DESC',
            [$salonId, self::CLOSED]
        );
    }

    /**
     * همهٔ تیکت‌ها، برای پنل پلتفرم.
     *
     * پیش‌فرض بازها و جواب‌داده‌ها بالا می‌آیند و بسته‌ها پایین: کاری
     * که مانده مهم‌تر از کاری است که تمام شده.
     *
     * @return array<int,array>
     */
    public function all(string $status = ''): array
    {
        $sql = 'SELECT t.*, s.name AS salon_name, s.slug AS salon_slug, s.is_active AS salon_active,
                       u.phone AS opener_phone, u.name AS opener_name,
                       (SELECT COUNT(*) FROM support_messages m WHERE m.ticket_id = t.id) AS message_count
                  FROM support_tickets t
                  JOIN salons s ON s.id = t.salon_id
                  LEFT JOIN users u ON u.id = t.opened_by_user_id';
        $args = [];

        if ($status !== '') {
            $sql .= ' WHERE t.status = ?';
            $args[] = $status;
        }

        // ترتیب: منتظر ما، بعد منتظر آن‌ها، بعد بسته‌ها.
        $sql .= " ORDER BY FIELD(t.status, 'open', 'answered', 'closed'), t.last_message_at DESC";

        return DB::select($sql, $args);
    }

    /** چند تیکت منتظر جوابِ ماست. */
    public function waitingCount(): int
    {
        $row = DB::selectOne(
            'SELECT COUNT(*) AS c FROM support_tickets WHERE status = ?',
            [self::OPEN]
        );

        return (int) ($row['c'] ?? 0);
    }

    /** @return array<string,int> شمارش هر وضعیت، برای فیلترهای صفحه. */
    public function counts(): array
    {
        $out = [self::OPEN => 0, self::ANSWERED => 0, self::CLOSED => 0];

        foreach (DB::select('SELECT status, COUNT(*) AS c FROM support_tickets GROUP BY status') as $row) {
            $out[(string) $row['status']] = (int) $row['c'];
        }

        return $out;
    }
}
