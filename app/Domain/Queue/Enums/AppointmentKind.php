<?php

declare(strict_types=1);

namespace App\Domain\Queue\Enums;

/**
 * نوبت رزروشده و مراجعهٔ حضوری، یک جدول‌اند و فقط با این enum از هم جدا می‌شوند.
 *
 * دلیلش در ADR-0003 آمده: اگر در دیتابیس دو چیز باشند، دیر یا زود در رابط کاربری
 * هم دو چیز می‌شوند — و آن‌وقت «صف واحد» را از دست داده‌ایم.
 */
enum AppointmentKind: string
{
    case Booked = 'booked';
    case WalkIn = 'walkin';
}
