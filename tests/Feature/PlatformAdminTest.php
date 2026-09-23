<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\DB;
use App\Domain\Platform\AuditLog;
use App\Domain\Platform\InvoiceRepository;
use App\Domain\Platform\PlanRepository;
use App\Domain\Platform\SalonAdminRepository;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * پنل پلتفرم.
 *
 * اینجا جایی است که پول و دسترسی جابه‌جا می‌شوند: پلن یک سالن عوض
 * می‌شود، صورتحساب صادر می‌شود، و کسی مدیر پلتفرم می‌شود. هر سه
 * اشتباه‌شان گران است و هیچ‌کدام سر و صدا نمی‌کنند.
 */
final class PlatformAdminTest extends TestCase
{
    private int $salonId;

    protected function setUp(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach (['platform_invoices', 'audit_logs', 'appointments', 'salon_user',
                  'staff', 'customers', 'users', 'salons'] as $t) {
            DB::statement("DELETE FROM `{$t}` WHERE 1");
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->salonId = (int) DB::insert('salons', [
            'slug' => 'plat-' . bin2hex(random_bytes(4)),
            'name' => 'سالن آزمایش',
            'is_active' => 1,
            'plan_code' => 'trial',
            'seats' => 1,
        ]);
    }

    // ─── قیمت‌گذاری ──────────────────────────────────────────────────

    public function test_the_base_price_is_the_plan_price(): void
    {
        $plans = new PlanRepository();

        self::assertSame(9900000, $plans->monthlyPriceFor('salon', 3));
    }

    public function test_extra_seats_are_charged(): void
    {
        // پلن «سالن» تا سه صندلی دارد؛ صندلی چهارم و پنجم اضافه‌اند.
        // بدون این حساب، سالن پنج‌صندلی پول سه صندلی می‌داد.
        $plans = new PlanRepository();

        self::assertSame(9900000 + (2 * 2500000), $plans->monthlyPriceFor('salon', 5));
    }

    public function test_a_plan_without_extra_seats_refuses_more(): void
    {
        $plans = new PlanRepository();

        self::assertTrue($plans->allowsSeats('solo', 1));
        self::assertFalse($plans->allowsSeats('solo', 2), 'تک‌صندلی صندلی اضافه ندارد.');
        self::assertTrue($plans->allowsSeats('salon', 9), 'سالن صندلی اضافه می‌پذیرد.');
        self::assertTrue($plans->allowsSeats('chain', 99), 'زنجیره نامحدود است.');
    }

    public function test_an_unknown_plan_costs_nothing_and_allows_nothing(): void
    {
        $plans = new PlanRepository();

        self::assertSame(0, $plans->monthlyPriceFor('ghost', 3));
        self::assertFalse($plans->allowsSeats('ghost', 1));
    }

    // ─── صورتحساب ────────────────────────────────────────────────────

    public function test_issuing_twice_for_one_month_makes_one_invoice(): void
    {
        // دو بار کلیک کردن نباید دو صورتحساب بسازد — اختلاف حساب با
        // مشتری از همین‌جا شروع می‌شود.
        $repo = new InvoiceRepository();
        $month = new DateTimeImmutable('2026-03-15');

        $first = $repo->issue($this->salonId, 'salon', 9900000, $month);
        $second = $repo->issue($this->salonId, 'salon', 9900000, $month->modify('+5 days'));

        self::assertSame($first, $second);
        self::assertCount(1, $repo->forSalon($this->salonId));
    }

    public function test_the_period_covers_the_whole_month(): void
    {
        $repo = new InvoiceRepository();
        $repo->issue($this->salonId, 'salon', 9900000, new DateTimeImmutable('2026-03-15'));

        $invoice = $repo->forSalon($this->salonId)[0];

        self::assertSame('2026-03-01', $invoice['period_start']);
        self::assertSame('2026-03-31', $invoice['period_end']);
    }

    public function test_paying_records_when(): void
    {
        $repo = new InvoiceRepository();
        $id = $repo->issue($this->salonId, 'salon', 9900000, new DateTimeImmutable('today'));

        $repo->markPaid($id);
        $invoice = $repo->find($id);

        self::assertSame('paid', $invoice['status']);
        self::assertNotNull($invoice['paid_at']);
    }

    public function test_a_past_period_becomes_overdue(): void
    {
        $repo = new InvoiceRepository();
        $old = $repo->issue($this->salonId, 'salon', 9900000, new DateTimeImmutable('-3 months'));
        $now = $repo->issue($this->salonId, 'salon', 9900000, new DateTimeImmutable('today'));

        $repo->markOverdue();

        self::assertSame('overdue', $repo->find($old)['status']);
        self::assertSame('pending', $repo->find($now)['status'], 'ماه جاری هنوز معوق نیست.');
    }

    public function test_a_cancelled_invoice_never_becomes_overdue(): void
    {
        $repo = new InvoiceRepository();
        $id = $repo->issue($this->salonId, 'salon', 9900000, new DateTimeImmutable('-3 months'));
        $repo->cancel($id);

        $repo->markOverdue();

        self::assertSame('cancelled', $repo->find($id)['status']);
    }

    // ─── سالن‌ها ─────────────────────────────────────────────────────

    public function test_deactivating_keeps_the_data(): void
    {
        // سالنی که اشتراکش تمام شده ممکن است برگردد. رفتنِ پروندهٔ
        // مشتری‌هایش یعنی دیگر برنمی‌گردد.
        DB::insert('customers', [
            'salon_id' => $this->salonId,
            'name' => 'مشتری',
            'phone' => '+989120000009',
        ]);

        $repo = new SalonAdminRepository();
        $repo->setActive($this->salonId, false);

        self::assertSame(0, (int) $repo->find($this->salonId)['is_active']);
        self::assertSame(
            1,
            (int) DB::selectOne(
                'SELECT COUNT(*) AS c FROM customers WHERE salon_id = ?',
                [$this->salonId]
            )['c']
        );
    }

    public function test_a_salon_with_no_bookings_shows_as_quiet(): void
    {
        $quiet = (new SalonAdminRepository())->goingQuiet();
        $ids = array_column($quiet, 'id');

        self::assertContains($this->salonId, array_map('intval', $ids));
    }

    public function test_a_deactivated_salon_is_not_chased(): void
    {
        // سالن غیرفعال، ساکت است چون ما بستیمش — نه چون دارد می‌رود.
        (new SalonAdminRepository())->setActive($this->salonId, false);

        $ids = array_map('intval', array_column(
            (new SalonAdminRepository())->goingQuiet(),
            'id'
        ));

        self::assertNotContains($this->salonId, $ids);
    }

    // ─── رد پا ───────────────────────────────────────────────────────

    public function test_actions_leave_a_trace(): void
    {
        AuditLog::record(AuditLog::PLAN_CHANGED, $this->salonId, 'salon', $this->salonId, [
            'from' => 'trial',
            'to' => 'salon',
        ]);

        $logs = AuditLog::recent(5, $this->salonId);

        self::assertCount(1, $logs);
        self::assertSame(AuditLog::PLAN_CHANGED, $logs[0]['action']);
        self::assertStringContainsString('trial', (string) $logs[0]['meta_json']);
    }

    public function test_every_action_has_a_persian_label(): void
    {
        // بدون این، صفحهٔ گزارش فعالیت رشته‌های انگلیسیِ کد را نشان
        // می‌دهد و کسی که دنبال «چرا پلن ما عوض شد» است چیزی نمی‌فهمد.
        $constants = (new \ReflectionClass(AuditLog::class))->getConstants();

        foreach ($constants as $name => $value) {
            if (!is_string($value) || $name === 'LABELS') {
                continue;
            }
            self::assertNotSame(
                $value,
                AuditLog::label($value),
                "کنش «{$value}» عنوان فارسی ندارد."
            );
        }
    }
}
