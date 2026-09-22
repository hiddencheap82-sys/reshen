<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\DB;
use App\Domain\Queue\DurationEstimator;
use App\Domain\Queue\EtaEngine;
use App\Domain\Queue\QueueOrderingService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * ضریب سرعتِ شخصیِ مشتری، از دیتابیس تا زمانِ وعده‌داده‌شده.
 *
 * چرا جدا از EtaAccuracyTest: آن تست یادگیری موتور را از روی سابقهٔ
 * خدمت می‌سنجد و به ضریب مشتری حساس نیست — با خراب کردنِ عمدیِ کوئریِ
 * مشتری‌ها، کل ۴۵۰ تست همچنان سبز می‌ماند. یعنی این مسیر پوشش نداشت:
 * اگر روزی آن کوئری بی‌صدا هیچ ردیفی برنگرداند، هر مشتری «متوسط»
 * فرض می‌شود و کسی خبردار نمی‌شود.
 */
final class CustomerPaceTest extends TestCase
{
    private int $salonId;
    private int $staffId;
    private int $serviceId;

    protected function setUp(): void
    {
        DurationEstimator::flushCache();

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach ([
            'duration_stats', 'appointment_items', 'appointments',
            'staff_service', 'services', 'working_hours', 'staff', 'customers', 'salons',
        ] as $t) {
            DB::statement("DELETE FROM `{$t}` WHERE 1");
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->salonId = (int) DB::insert('salons', [
            'slug' => 'pace-' . bin2hex(random_bytes(4)),
            'name' => 'سالن ضریب',
            'is_active' => 1,
        ]);

        $this->staffId = (int) DB::insert('staff', [
            'salon_id' => $this->salonId,
            'name' => 'آرایشگر',
            'is_active' => 1,
        ]);

        $this->serviceId = (int) DB::insert('services', [
            'salon_id' => $this->salonId,
            'name' => 'اصلاح مو',
            'duration_minutes' => 30,
            'price' => 100000,
            'is_active' => 1,
        ]);
    }

    public function test_slow_customer_gets_a_longer_promise_than_an_average_one(): void
    {
        $now = new DateTimeImmutable('2026-09-21 10:00:00');

        $average = $this->queue('میانگین', null, $now, 2);
        $slow = $this->queue('کند', 1.4, $now, 1);

        $etas = $this->compute($now);

        $baseline = $etas[$average]['expected_p50'];
        self::assertGreaterThan(0.0, $baseline, 'تخمین پایه باید عددی معنادار باشد');

        self::assertEqualsWithDelta(
            $baseline * 1.4,
            $etas[$slow]['expected_p50'],
            0.01,
            'ضریب ۱.۴ باید مستقیم روی مدتِ تخمینی بنشیند'
        );
    }

    public function test_an_absurd_factor_is_clamped_instead_of_trusted(): void
    {
        $now = new DateTimeImmutable('2026-09-21 10:00:00');

        $average = $this->queue('میانگین', null, $now, 2);
        // دادهٔ خراب یا یک روز فاجعه‌بار نباید صف را سه‌برابر کند.
        $absurd = $this->queue('پرت', 9.0, $now, 1);

        $etas = $this->compute($now);

        self::assertEqualsWithDelta(
            $etas[$average]['expected_p50'] * 1.5,
            $etas[$absurd]['expected_p50'],
            0.01,
            'ضریب باید به سقف ۱.۵ محدود شود'
        );
    }

    public function test_the_factor_survives_the_salon_scoped_lookup(): void
    {
        // سالن دوم با مشتریِ خودش: کوئریِ سالن‌محور نباید مشتریِ سالن
        // درست را هم قربانی کند.
        DB::insert('salons', [
            'slug' => 'other-' . bin2hex(random_bytes(4)),
            'name' => 'سالن دیگر',
            'is_active' => 1,
        ]);

        $now = new DateTimeImmutable('2026-09-21 10:00:00');
        $slow = $this->queue('کند', 1.4, $now, 1);

        $etas = $this->compute($now);

        self::assertGreaterThan(
            30.0,
            $etas[$slow]['expected_p50'],
            'ضریب باید اعمال شده باشد، نه اینکه مشتری گم شود و ۳۰ دقیقهٔ اسمی برگردد'
        );
    }

    private function queue(string $name, ?float $factor, DateTimeImmutable $now, int $minutesAgo): int
    {
        $customerId = (int) DB::insert('customers', [
            'salon_id' => $this->salonId,
            'name' => $name,
            'phone' => '+98912' . str_pad((string) random_int(1000000, 9999999), 7, '0', STR_PAD_LEFT),
            'duration_factor' => $factor,
        ]);

        $id = (int) DB::insert('appointments', [
            'salon_id' => $this->salonId,
            'public_token' => bin2hex(random_bytes(6)),
            'customer_id' => $customerId,
            'staff_id' => $this->staffId,
            'kind' => 'walkin',
            'status' => 'queued',
            'queued_at' => $now->modify("-{$minutesAgo} minutes")->format('Y-m-d H:i:s'),
        ]);

        DB::insert('appointment_items', [
            'salon_id' => $this->salonId,
            'appointment_id' => $id,
            'service_id' => $this->serviceId,
            'price' => 100000,
            'duration_minutes' => 30,
        ]);

        return $id;
    }

    /** @return array<int,array> */
    private function compute(DateTimeImmutable $now): array
    {
        $active = (new \App\Domain\Appointment\AppointmentRepository())
            ->activeForStaff($this->salonId, $this->staffId);
        $ordered = (new QueueOrderingService())->order($active, $now);

        return (new EtaEngine())->computeForStaffQueue($ordered, $now);
    }
}
