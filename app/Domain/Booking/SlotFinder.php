<?php

declare(strict_types=1);

namespace App\Domain\Booking;

use App\Core\DB;
use App\Support\Jalali;
use DateTimeImmutable;

/**
 * جستجوی سانس‌های آزاد برای رزرو آنلاین.
 *
 * ساعت کاری آرایشگر را در آن روز قدم‌به‌قدم می‌پیماید و هر زمان شروعی را
 * نگه می‌دارد که بازهٔ [شروع، شروع + مدت خدمت] با هیچ نوبت رزروشده،
 * مرخصی، یا **استراحت روزانه** تداخل نداشته باشد.
 *
 * طول قدم (سانس‌بندی) از تنظیمات سالن می‌آید، نه از یک عدد ثابت در کد:
 * سالنی که کوتاه‌ترین خدمتش ۲۰ دقیقه است، نباید سانس ۱۵ دقیقه‌ای ببیند.
 */
final class SlotFinder
{
    /** وقتی سالن چیزی تنظیم نکرده باشد. */
    private const DEFAULT_STEP_MINUTES = 15;

    /** @return string[] "H:i" start times */
    public function freeSlotsForStaff(int $salonId, int $staffId, DateTimeImmutable $date, int $durationMinutes): array
    {
        $weekday = Jalali::weekday($date);

        $salonHours = DB::selectOne(
            'SELECT * FROM working_hours WHERE salon_id = ? AND staff_id IS NULL AND weekday = ?',
            [$salonId, $weekday]
        );
        $staffHours = DB::selectOne(
            'SELECT * FROM working_hours WHERE salon_id = ? AND staff_id = ? AND weekday = ?',
            [$salonId, $staffId, $weekday]
        );

        $hours = $this->mergeHours($salonHours, $staffHours);

        if ($hours === null || (int) $hours['is_closed'] === 1) {
            return [];
        }

        $dateStr = $date->format('Y-m-d');
        if ($this->isHoliday($dateStr)) {
            return [];
        }

        $dayStart = new DateTimeImmutable($dateStr . ' ' . $hours['opens_at']);
        $dayEnd = new DateTimeImmutable($dateStr . ' ' . $hours['closes_at']);
        $now = new DateTimeImmutable();

        $busy = $this->busyIntervals($salonId, $staffId, $dateStr);

        // استراحت روزانه مثل یک نوبتِ اشغال رفتار می‌کند. اگر اینجا
        // نیاید، مشتری برای ساعتی نوبت می‌گیرد که کسی سر کار نیست.
        if (!empty($hours['break_start']) && !empty($hours['break_end'])) {
            $breakFrom = new DateTimeImmutable($dateStr . ' ' . $hours['break_start']);
            $breakTo = new DateTimeImmutable($dateStr . ' ' . $hours['break_end']);
            if ($breakTo > $breakFrom) {
                $busy[] = [$breakFrom, $breakTo];
            }
        }

        $step = $this->stepMinutes($salonId);

        $slots = [];
        $cursor = $dayStart;
        while ($cursor->modify("+{$durationMinutes} minutes") <= $dayEnd) {
            $slotEnd = $cursor->modify("+{$durationMinutes} minutes");
            if ($cursor > $now && !$this->overlaps($cursor, $slotEnd, $busy)) {
                $slots[] = $cursor->format('H:i');
            }
            $cursor = $cursor->modify('+' . $step . ' minutes');
        }

        return $slots;
    }

    /**
     * ساعت کاری آرایشگر روی ساعت کاری سالن.
     *
     * ردیف اختصاصی آرایشگر، ساعت باز و بستهٔ خودش را تعیین می‌کند. ولی
     * **استراحت را پاک نمی‌کند**: تعطیلی ظهر یک واقعیتِ سطحِ سالن است
     * (مغازه بسته است)، نه سلیقهٔ یک آرایشگر. اگر ردیف آرایشگر استراحت
     * نداشته باشد، استراحت سالن سر جایش می‌ماند.
     *
     * بدون این ادغام، لحظه‌ای که برای یک آرایشگر ساعت اختصاصی ثبت شود،
     * استراحتی که صاحب سالن در تنظیمات گذاشته بی‌صدا از کار می‌افتد و
     * مشتری برای وسط تعطیلی نوبت می‌گیرد.
     *
     * @param array<string,mixed>|null $salon
     * @param array<string,mixed>|null $staff
     * @return array<string,mixed>|null
     */
    private function mergeHours(?array $salon, ?array $staff): ?array
    {
        if ($staff === null) {
            return $salon;
        }

        if ($salon !== null && empty($staff['break_start'])) {
            $staff['break_start'] = $salon['break_start'] ?? null;
            $staff['break_end'] = $salon['break_end'] ?? null;
        }

        return $staff;
    }

    /**
     * طول سانس سالن، با حفاظ.
     *
     * صفر یا منفی، حلقهٔ بالا را بی‌نهایت می‌کند؛ عدد خیلی بزرگ هم عملاً
     * رزرو را غیرممکن. پس بین ۵ تا ۱۲۰ دقیقه بریده می‌شود.
     */
    private function stepMinutes(int $salonId): int
    {
        static $cache = [];

        if (!isset($cache[$salonId])) {
            $row = DB::selectOne('SELECT slot_step_minutes FROM salons WHERE id = ?', [$salonId]);
            $value = (int) ($row['slot_step_minutes'] ?? self::DEFAULT_STEP_MINUTES);
            $cache[$salonId] = max(5, min(120, $value ?: self::DEFAULT_STEP_MINUTES));
        }

        return $cache[$salonId];
    }

    /** @return array<int,array{0:DateTimeImmutable,1:DateTimeImmutable}> */
    private function busyIntervals(int $salonId, int $staffId, string $dateStr): array
    {
        $intervals = [];

        $appts = DB::select(
            "SELECT scheduled_at, queued_at FROM appointments
             WHERE salon_id = ? AND staff_id = ? AND DATE(COALESCE(scheduled_at, queued_at)) = ?
             AND status IN ('confirmed','queued','in_chair')",
            [$salonId, $staffId, $dateStr]
        );
        foreach ($appts as $a) {
            $start = new DateTimeImmutable($a['scheduled_at'] ?? $a['queued_at']);
            $intervals[] = [$start, $start->modify('+30 minutes')];
        }

        $offs = DB::select(
            'SELECT starts_at, ends_at FROM time_offs WHERE salon_id = ? AND (staff_id = ? OR staff_id IS NULL)
             AND DATE(starts_at) <= ? AND DATE(ends_at) >= ?',
            [$salonId, $staffId, $dateStr, $dateStr]
        );
        foreach ($offs as $o) {
            $intervals[] = [new DateTimeImmutable($o['starts_at']), new DateTimeImmutable($o['ends_at'])];
        }

        return $intervals;
    }

    private function overlaps(DateTimeImmutable $start, DateTimeImmutable $end, array $busy): bool
    {
        foreach ($busy as [$busyStart, $busyEnd]) {
            if ($start < $busyEnd && $end > $busyStart) {
                return true;
            }
        }

        return false;
    }

    private function isHoliday(string $dateStr): bool
    {
        return DB::selectOne('SELECT id FROM holidays WHERE gregorian_date = ?', [$dateStr]) !== null;
    }
}
