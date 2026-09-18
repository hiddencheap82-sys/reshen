<?php

declare(strict_types=1);

namespace App\Domain\Queue\Services;

use App\Domain\Queue\Enums\AppointmentKind;
use App\Domain\Queue\Enums\AppointmentStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * ترتیب صف یک صندلی.
 *
 * سه اولویت:
 *   ۱. روی صندلی
 *   ۲. رزروشده و «در پنجرهٔ حق تقدم» (±۱۰ دقیقه از ساعتش)
 *   ۳. بقیه، به ترتیب زمان ورود به صف
 *
 * پنجرهٔ حق تقدم، قاعده‌ای است که هم عادلانه است هم قابل توضیح به مشتریِ ایستاده:
 * کسی که رزرو کرده حقش محفوظ است، ولی اگر ۴۰ دقیقه دیر بیاید جلوی کسی که
 * نیم ساعت نشسته نمی‌افتد.
 *
 * @see docs/10-architecture/04-queue-eta-engine.md §۲
 */
final class QueueOrderer
{
    public function __construct(
        private readonly int $priorityWindowMinutes,
    ) {}

    /** @param Collection<int,object> $appointments */
    public function sort(Collection $appointments, CarbonImmutable $now): Collection
    {
        return $appointments
            ->sortBy(fn ($a) => $this->sortKey($a, $now))
            ->values();
    }

    /** @return array{int,int} */
    private function sortKey(object $appointment, CarbonImmutable $now): array
    {
        if ($appointment->status === AppointmentStatus::InChair) {
            return [0, 0];
        }

        if ($appointment->kind === AppointmentKind::Booked && $appointment->scheduled_at !== null) {
            $distance = abs($appointment->scheduled_at->diffInMinutes($now));

            if ($distance <= $this->priorityWindowMinutes) {
                return [1, $appointment->scheduled_at->getTimestamp()];
            }
        }

        // در تساوی، id کوچک‌تر جلوتر است — دو ثبت هم‌زمان نباید ترتیب غیرقطعی بدهند.
        $queuedAt = $appointment->queued_at ?? $appointment->scheduled_at;

        return [2, $queuedAt?->getTimestamp() ?? $appointment->id];
    }
}
