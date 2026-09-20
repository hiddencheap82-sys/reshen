<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\DB;
use App\Domain\Queue\EtaEngine;
use App\Domain\Queue\QueueOrderingService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * دقت موتور تخمین — شبیه‌سازی یک روز کاری.
 *
 * چرا این تست مهم‌ترین تستِ پروژه است: «زمانِ راست» تنها چیزی است که
 * این محصول را از بقیه جدا می‌کند. اگر تخمین بد باشد، مشتری یک بار
 * گول می‌خورد و دیگر برنمی‌گردد — و هیچ خطایی هم در لاگ نمی‌افتد.
 * بدون این تست، هر تغییری در موتور صف می‌توانست بی‌صدا خرابش کند.
 *
 * روش کار:
 *   ۱. یک روز با صف مشخص ساخته می‌شود
 *   ۲. هر بار که نفر بعدی روی صندلی می‌نشیند، تخمینِ **همان لحظه** برای
 *      همهٔ کسانی که در صف مانده‌اند ثبت می‌شود
 *   ۳. روز جلو می‌رود و هر نفر با مدت **واقعی**اش سرویس می‌گیرد
 *   ۴. اختلاف «چیزی که گفتیم» و «چیزی که شد» اندازه گرفته می‌شود
 *
 * آستانه از سند شاخص‌ها می‌آید: MAE زیر ۱۲ دقیقه قابل قبول، زیر ۷ خوب،
 * بالای ۱۸ یعنی خراب.
 */
final class EtaAccuracyTest extends TestCase
{
    private const MAE_ACCEPTABLE = 12.0;

    private int $salonId;
    private int $staffId;
    /** @var array<string,int> */
    private array $services = [];

    protected function setUp(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach ([
            'duration_stats', 'appointment_items', 'appointments',
            'staff_service', 'services', 'working_hours', 'staff', 'customers', 'salons',
        ] as $t) {
            DB::statement("DELETE FROM `{$t}` WHERE 1");
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->salonId = (int) DB::insert('salons', [
            'slug' => 'eta-' . bin2hex(random_bytes(4)),
            'name' => 'سالن شبیه‌سازی',
            'is_active' => 1,
        ]);

        $this->staffId = (int) DB::insert('staff', [
            'salon_id' => $this->salonId,
            'name' => 'آرایشگر',
            'is_active' => 1,
        ]);

        foreach (['اصلاح مو' => 30, 'اصلاح ریش' => 15, 'مو و ریش' => 45] as $name => $minutes) {
            $this->services[$name] = (int) DB::insert('services', [
                'salon_id' => $this->salonId,
                'name' => $name,
                'duration_minutes' => $minutes,
                'price' => 100000,
                'is_active' => 1,
            ]);
        }
    }

    /**
     * روز معمولی: مدت واقعی نزدیک به اسمی، با نوسان طبیعی.
     */
    public function test_mae_stays_within_target_on_a_normal_day(): void
    {
        ['mae' => $mae] = $this->runDay([
            // [خدمت، مدت واقعی به دقیقه]
            ['اصلاح مو', 28], ['اصلاح ریش', 17], ['مو و ریش', 47],
            ['اصلاح مو', 33], ['اصلاح مو', 26], ['اصلاح ریش', 14],
            ['مو و ریش', 43], ['اصلاح مو', 31],
        ]);

        self::assertLessThan(
            self::MAE_ACCEPTABLE,
            $mae,
            sprintf('MAE %.1f دقیقه است؛ آستانهٔ سند شاخص‌ها %.0f دقیقه', $mae, self::MAE_ACCEPTABLE)
        );
    }

    /**
     * روزی که هر نوبت کمی طول می‌کشد.
     *
     * اینجا خطا جمع می‌شود چون هر تأخیر به نفرات بعدی منتقل می‌شود —
     * همان چیزی که در سالن واقعی اتفاق می‌افتد. تخمین باید باز هم در
     * محدودهٔ قابل قبول بماند، وگرنه بعدازظهرهای شلوغ بی‌فایده است.
     */
    public function test_mae_survives_a_day_that_runs_long(): void
    {
        ['mae' => $mae] = $this->runDay([
            ['اصلاح مو', 38], ['اصلاح مو', 36], ['اصلاح ریش', 21],
            ['مو و ریش', 55], ['اصلاح مو', 37], ['اصلاح ریش', 20],
        ]);

        self::assertLessThan(
            18.0,
            $mae,
            sprintf('در روزِ کشدار MAE %.1f دقیقه شد؛ بالای ۱۸ یعنی خراب', $mae)
        );
    }

    /**
     * یادگیری باید واقعاً کمک کند.
     *
     * ادعای محصول این است که موتور از مدت‌های واقعی یاد می‌گیرد. تا
     * وقتی اندازه نگیریم، ادعاست. اینجا همان آرایشگر با همان خدمت، ولی
     * یک بار بدون حافظه و یک بار با ۱۰ نمونهٔ واقعیِ کندتر از عدد اسمی.
     *
     * سنجه: خطای تخمین باید **کم** شود، نه اینکه فقط عوض شود.
     */
    public function test_learning_from_real_durations_reduces_error(): void
    {
        $slowDay = [
            ['اصلاح مو', 42], ['اصلاح مو', 40], ['اصلاح مو', 43],
            ['اصلاح مو', 41], ['اصلاح مو', 44],
        ];

        ['mae' => $before] = $this->runDay($slowDay);

        // این آرایشگر واقعاً ۴۲ دقیقه‌ای است، نه ۳۰ دقیقهٔ اسمی
        $this->teachDuration('اصلاح مو', [40, 41, 42, 43, 44, 41, 42, 43, 40, 42]);

        ['mae' => $after] = $this->runDay($slowDay, 3.0, false);

        self::assertLessThan(
            $before,
            $after,
            sprintf('یادگیری باید خطا را کم کند؛ پیش از یادگیری %.1f و پس از آن %.1f دقیقه', $before, $after)
        );

        self::assertLessThan(
            self::MAE_ACCEPTABLE,
            $after,
            sprintf('پس از یادگیری MAE باید زیر آستانه برود؛ %.1f دقیقه شد', $after)
        );
    }

    /**
     * نمونه‌های واقعی را در حافظهٔ موتور می‌نشاند.
     *
     * مستقیم در duration_stats نوشته می‌شود نه از راه DurationLearner:
     * آن کلاس به نوبتِ تمام‌شده و مشتری نیاز دارد و اینجا هدف، سنجشِ
     * تخمین است نه مسیرِ ثبت.
     *
     * @param int[] $minutes
     */
    private function teachDuration(string $serviceName, array $minutes): void
    {
        sort($minutes);
        $count = count($minutes);
        $p50 = $minutes[(int) floor(($count - 1) * 0.5)];
        $p80 = $minutes[(int) floor(($count - 1) * 0.8)];

        DB::insert('duration_stats', [
            'salon_id' => $this->salonId,
            'staff_id' => $this->staffId,
            'service_id' => $this->services[$serviceName],
            'sample_count' => $count,
            'p50_minutes' => $p50,
            'p80_minutes' => $p80,
        ]);
    }

    /** تخمین هرگز نباید «همین حالا» بدهد به کسی که چند نفر جلوتر دارد. */
    public function test_never_promises_now_to_someone_with_people_ahead(): void
    {
        $now = new DateTimeImmutable('2026-03-10 10:00:00');
        $ids = $this->buildQueue([['اصلاح مو', 30], ['اصلاح مو', 30], ['اصلاح مو', 30]], $now);

        // اولی روی صندلی
        DB::update('appointments', [
            'status' => 'in_chair',
            'actual_start_at' => $now->format('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $ids[0]]);

        $etas = $this->computeAt($now);

        foreach ([1, 2] as $position) {
            $start = $etas[$ids[$position]]['start_p50'];
            self::assertGreaterThan(
                $now->getTimestamp(),
                $start->getTimestamp(),
                'کسی که نفر جلوتر دارد نباید تخمین «همین حالا» بگیرد'
            );
        }
    }

    /** بازهٔ بالا (p80) هرگز نباید زیر تخمین میانه (p50) بیفتد. */
    public function test_upper_bound_is_never_below_the_median(): void
    {
        $now = new DateTimeImmutable('2026-03-10 10:00:00');
        $ids = $this->buildQueue([['مو و ریش', 45], ['اصلاح ریش', 15], ['اصلاح مو', 30]], $now);
        $etas = $this->computeAt($now);

        foreach ($ids as $id) {
            self::assertGreaterThanOrEqual(
                $etas[$id]['start_p50']->getTimestamp(),
                $etas[$id]['start_p80']->getTimestamp(),
                'حد بالا نباید زودتر از تخمین میانه باشد'
            );
        }
    }

    /**
     * تخمینِ نفر بعدی باید از تخمینِ نفر قبلی دیرتر باشد.
     *
     * بدیهی به نظر می‌رسد ولی اگر ترتیب صف و محاسبه از هم جدا بیفتند،
     * دو نفر تخمین یکسان می‌گیرند و هر دو سر یک ساعت می‌آیند.
     */
    public function test_estimates_increase_along_the_queue(): void
    {
        $now = new DateTimeImmutable('2026-03-10 10:00:00');
        $ids = $this->buildQueue([['اصلاح مو', 30], ['اصلاح مو', 30], ['اصلاح مو', 30], ['اصلاح مو', 30]], $now);
        $etas = $this->computeAt($now);

        $previous = null;
        foreach ($ids as $index => $id) {
            $start = $etas[$id]['start_p50']->getTimestamp();
            if ($previous !== null) {
                self::assertGreaterThan($previous, $start, "تخمین نفر {$index} باید دیرتر از نفر قبلی باشد");
            }
            $previous = $start;
        }
    }

    // ─── ابزار شبیه‌سازی ────────────────────────────────────────────

    /**
     * یک روز کامل را جلو می‌برد و میانگین قدرمطلق خطا را برمی‌گرداند.
     *
     * نکتهٔ ظریفی که اول از قلم افتاد: خطا باید بین «چیزی که **از قبل**
     * به مشتری گفتیم» و «لحظه‌ای که واقعاً نشست» اندازه گرفته شود.
     * اگر تخمین را در همان لحظهٔ نشستن بگیری، آن نفر در جایگاه صفر است
     * و تخمینش دقیقاً همان «الان» می‌شود — خطا همیشه صفر درمی‌آید و
     * تست سبزِ بی‌معنی می‌دهد. (همین اتفاق افتاد و با چاپ کردن عدد
     * معلوم شد.)
     *
     * پس تخمینِ هر نفر یک بار، اولِ روز و پیش از شروع سرویس‌ها، ثبت
     * می‌شود — همان چیزی که مشتری موقع ورود به صف می‌بیند.
     *
     * `$turnoverMinutes` فاصلهٔ بین دو مشتری است: حساب کردن، بلند شدن،
     * جارو زدن، آمدن نفر بعد. موتور برای همین، بافر دارد. شبیه‌سازی‌ای
     * که این فاصله را صفر بگیرد، موتور را به‌خاطر چیزی جریمه می‌کند که
     * در سالن واقعی وجود دارد.
     *
     * @param array<int,array{0:string,1:int}> $plan خدمت و مدت واقعی
     * @return array{mae:float,bias:float}
     */
    private function runDay(array $plan, float $turnoverMinutes = 3.0, bool $resetQueue = true): array
    {
        if ($resetQueue) {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            DB::statement('DELETE FROM appointment_items WHERE 1');
            DB::statement('DELETE FROM appointments WHERE 1');
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        } else {
            // صف را پاک کن ولی حافظهٔ یادگیری را نگه دار
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            DB::statement('DELETE FROM appointment_items WHERE 1');
            DB::statement('DELETE FROM appointments WHERE 1');
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        $now = new DateTimeImmutable('2026-03-10 09:00:00');
        $ids = $this->buildQueue($plan, $now);

        // آنچه اولِ روز به هر نفر وعده داده شد
        $promised = [];
        foreach ($this->computeAt($now) as $id => $eta) {
            $promised[$id] = $eta['start_p50'];
        }

        $errors = [];
        $signed = [];
        $clock = $now;

        foreach ($plan as $index => [$serviceName, $actualMinutes]) {
            $id = $ids[$index];

            // واقعاً می‌نشیند
            DB::update('appointments', [
                'status' => 'in_chair',
                'actual_start_at' => $clock->format('Y-m-d H:i:s'),
            ], 'id = :id', ['id' => $id]);

            if (isset($promised[$id])) {
                // علامت‌دار: مثبت یعنی دیرتر از وعده نشست
                $signed[] = ($clock->getTimestamp() - $promised[$id]->getTimestamp()) / 60;
                $errors[] = abs($signed[count($signed) - 1]);
            }

            // سرویس می‌گیرد و تمام می‌شود
            $clock = $clock->modify('+' . $actualMinutes . ' minutes');
            DB::update('appointments', [
                'status' => 'completed',
                'actual_end_at' => $clock->format('Y-m-d H:i:s'),
            ], 'id = :id', ['id' => $id]);

            // فاصله تا مشتری بعدی
            $clock = $clock->modify('+' . (int) round($turnoverMinutes) . ' minutes');
        }

        self::assertSame(count($plan), count($errors), 'برای هر نفر باید یک خطا اندازه گرفته شود');

        return [
            'mae' => array_sum($errors) / count($errors),
            'bias' => array_sum($signed) / count($signed),
        ];
    }

    /**
     * صف روز را می‌سازد و شناسه‌ها را به ترتیب برمی‌گرداند.
     *
     * @param array<int,array{0:string,1:int}> $plan
     * @return int[]
     */
    private function buildQueue(array $plan, DateTimeImmutable $now): array
    {
        $ids = [];

        foreach ($plan as $index => [$serviceName, $_actual]) {
            $customerId = (int) DB::insert('customers', [
                'salon_id' => $this->salonId,
                'name' => 'مشتری ' . ($index + 1),
                'phone' => '+98912' . str_pad((string) (1000000 + $index), 7, '0', STR_PAD_LEFT),
            ]);

            $id = (int) DB::insert('appointments', [
                'salon_id' => $this->salonId,
                'public_token' => bin2hex(random_bytes(6)),
                'customer_id' => $customerId,
                'staff_id' => $this->staffId,
                'kind' => 'walkin',
                'status' => 'queued',
                // به ترتیب وارد صف شده‌اند
                'queued_at' => $now->modify('-' . (count($plan) - $index) . ' minutes')->format('Y-m-d H:i:s'),
            ]);

            $serviceId = $this->services[$serviceName];
            DB::insert('appointment_items', [
                'salon_id' => $this->salonId,
                'appointment_id' => $id,
                'service_id' => $serviceId,
                'price' => 100000,
                'duration_minutes' => (int) DB::selectOne(
                    'SELECT duration_minutes FROM services WHERE id = ?',
                    [$serviceId]
                )['duration_minutes'],
            ]);

            $ids[] = $id;
        }

        return $ids;
    }

    /** @return array<int,array> */
    private function computeAt(DateTimeImmutable $now): array
    {
        $active = (new \App\Domain\Queue\AppointmentRepository())
            ->activeForStaff($this->salonId, $this->staffId);
        $ordered = (new QueueOrderingService())->order($active, $now);

        return (new EtaEngine())->computeForStaffQueue($ordered, $now);
    }
}
