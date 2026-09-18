<?php

declare(strict_types=1);

namespace App\Domain\Queue\Enums;

enum AppointmentStatus: string
{
    /** رزرو شده ولی هنوز تأیید نشده (در انتظار بیعانه یا تأیید پیامکی). */
    case Pending = 'pending';

    /** تأییدشده، ولی هنوز روز نوبت نرسیده. */
    case Confirmed = 'confirmed';

    /** در صف امروز است و منتظر نوبتش. */
    case Queued = 'queued';

    /** روی صندلی — حداکثر یک نفر به ازای هر آرایشگر. */
    case InChair = 'in_chair';

    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoShow    = 'no_show';

    /** آیا این وضعیت در محاسبهٔ صف زنده شرکت می‌کند؟ */
    public function isActiveInQueue(): bool
    {
        return in_array($this, [self::Queued, self::InChair], true);
    }

    /** آیا نوبت به سرانجام رسید؟ (شاخص ستارهٔ شمالی) */
    public function isFulfilled(): bool
    {
        return $this === self::Completed;
    }
}
