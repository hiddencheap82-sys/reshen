<?php

declare(strict_types=1);

namespace App\Domain\Platform;

use App\Core\DB;
use DateTimeImmutable;

/**
 * صورتحساب اشتراک سالن‌ها.
 *
 * عمداً ساده: صورتحساب اینجا فقط *ثبت* می‌شود، پرداختش بیرون از
 * برنامه انجام می‌گیرد (کارت به کارت یا فاکتور رسمی) و بعد دستی
 * «پرداخت شد» می‌خورد.
 *
 * چرا خودکار نیست: در ایران پرداخت دوره‌ای خودکار برای کسب‌وکار کوچک
 * جا نیفتاده، و درگاه‌ها هم برای اشتراک ساخته نشده‌اند. یک سیستم
 * پرداختِ خودکارِ نیم‌کاره بدتر از نبودنش است — چون آدم به آن تکیه
 * می‌کند.
 */
final class InvoiceRepository
{
    /**
     * صورتحساب یک ماه برای یک سالن.
     *
     * دوباره‌سازی بی‌خطر است: اگر برای همان بازه صورتحسابی باشد،
     * چیزی ساخته نمی‌شود و همان برمی‌گردد. بدون این، یک بار دو
     * کلیک کردن یعنی دو صورتحساب برای یک ماه.
     */
    public function issue(int $salonId, string $planCode, int $amount, DateTimeImmutable $periodStart): int
    {
        $start = $periodStart->modify('first day of this month');
        $end = $start->modify('last day of this month');

        $existing = DB::selectOne(
            'SELECT id FROM platform_invoices
              WHERE salon_id = ? AND period_start = ?',
            [$salonId, $start->format('Y-m-d')]
        );

        if ($existing !== null) {
            return (int) $existing['id'];
        }

        return (int) DB::insert('platform_invoices', [
            'salon_id' => $salonId,
            'period_start' => $start->format('Y-m-d'),
            'period_end' => $end->format('Y-m-d'),
            'plan_code' => $planCode,
            'amount' => $amount,
            'status' => 'pending',
        ]);
    }

    /** @return array<int,array> */
    public function all(string $status = ''): array
    {
        $sql = 'SELECT i.*, s.name AS salon_name, s.slug AS salon_slug
                  FROM platform_invoices i
                  JOIN salons s ON s.id = i.salon_id';
        $args = [];

        if ($status !== '') {
            $sql .= ' WHERE i.status = ?';
            $args[] = $status;
        }
        $sql .= ' ORDER BY i.period_start DESC, i.id DESC';

        return DB::select($sql, $args);
    }

    /** @return array<int,array> */
    public function forSalon(int $salonId): array
    {
        return DB::select(
            'SELECT * FROM platform_invoices WHERE salon_id = ? ORDER BY period_start DESC',
            [$salonId]
        );
    }

    public function find(int $id): ?array
    {
        return DB::selectOne('SELECT * FROM platform_invoices WHERE id = ?', [$id]);
    }

    public function markPaid(int $id): void
    {
        DB::update('platform_invoices', [
            'status' => 'paid',
            'paid_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $id]);
    }

    public function cancel(int $id): void
    {
        DB::update('platform_invoices', ['status' => 'cancelled'], 'id = :id', ['id' => $id]);
    }

    /**
     * صورتحساب‌هایی که موعدشان گذشته.
     *
     * «گذشته» یعنی دورهٔ صورتحساب تمام شده و هنوز پرداخت نشده. این را
     * خودکار روی وضعیت می‌نویسیم تا فهرست پرداخت‌نشده‌ها راست بگوید.
     */
    public function markOverdue(): void
    {
        DB::statement(
            "UPDATE platform_invoices
                SET status = 'overdue'
              WHERE status = 'pending' AND period_end < CURDATE()"
        );
    }

    /** جمع مبالغ، به تفکیک وضعیت. */
    public function totals(): array
    {
        $rows = DB::select(
            'SELECT status, COUNT(*) AS count, COALESCE(SUM(amount), 0) AS total
               FROM platform_invoices GROUP BY status'
        );

        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row['status']] = [
                'count' => (int) $row['count'],
                'total' => (int) $row['total'],
            ];
        }

        return $out;
    }
}
