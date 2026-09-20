<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Domain\Queue\AppointmentRepository;
use App\Domain\Staff\StaffRepository;
use App\Support\JalaliCalendar;
use DateTimeImmutable;

/**
 * رزروهای زمان‌دار (سانس‌های گرفته‌شده).
 *
 * چرا جدا از «صف زنده»: صف، حالِ سالن است — چه کسی روی صندلی است و چه
 * کسی بعدی. رزرو، آیندهٔ سالن است. آرایشگر صبح که می‌آید باید بتواند
 * بپرسد «امروز چند تا وقت دارم؟» و جواب بگیرد؛ این سؤال را صف جواب
 * نمی‌دهد چون رزروِ ساعت ۶ عصر هنوز وارد صف نشده.
 *
 * بدون این صفحه، رزرو آنلاین یک‌طرفه بود: مشتری سانس می‌گرفت و سالن
 * تا لحظهٔ آمدنش خبردار نمی‌شد. (منوی کناری هم به مسیری لینک می‌داد
 * که وجود نداشت و ۴۰۴ می‌گرفت.)
 */
final class BookingsController extends Controller
{
    /** چند روز جلوتر را یک‌جا نشان بدهیم. */
    private const HORIZON_DAYS = 13;

    public function index(Request $request): Response
    {
        $salonId = Auth::salonId();

        $from = $this->parseDate((string) $request->query('from', ''), new DateTimeImmutable('today'));
        $to = $from->modify('+' . self::HORIZON_DAYS . ' days');

        $repo = new AppointmentRepository();

        // آرایشگر فقط رزروهای خودش را می‌بیند؛ صاحب و مدیر، همه را.
        // همان قاعدهٔ صفحهٔ صف (سند امنیت، بخش ۴).
        $onlyMine = !in_array(Auth::role(), ['owner', 'manager', 'reception'], true);
        $staffFilter = $onlyMine ? Auth::staffId() : null;

        $rows = $repo->scheduledBetween(
            $salonId,
            $from->format('Y-m-d'),
            $to->format('Y-m-d'),
            $staffFilter
        );

        return $this->page('layouts.panel', 'panel.bookings.index', [
            'title' => 'رزروها',
            'days' => $this->groupByDay($rows, $from, $to),
            'counts' => $repo->scheduledCounts($salonId, $from->format('Y-m-d'), $to->format('Y-m-d')),
            'from' => $from,
            'to' => $to,
            'prev' => $from->modify('-' . (self::HORIZON_DAYS + 1) . ' days')->format('Y-m-d'),
            'next' => $to->modify('+1 day')->format('Y-m-d'),
            'isToday' => $from->format('Y-m-d') === date('Y-m-d'),
            'onlyMine' => $onlyMine,
            'staffList' => (new StaffRepository())->all($salonId, true),
        ]);
    }

    /**
     * هر روزِ بازه یک خانه می‌گیرد، حتی روزهای خالی.
     *
     * روزِ خالی هم خبر است: «فردا هیچ رزروی نداری» چیزی است که صاحب
     * سالن باید ببیند، نه اینکه روز از فهرست غیب شود و فکر کند بارگذاری
     * نشده.
     *
     * @param array<int,array> $rows
     * @return array<int,array{date:DateTimeImmutable,label:string,rows:array}>
     */
    private function groupByDay(array $rows, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $byDate = [];
        foreach ($rows as $r) {
            $byDate[substr((string) $r['scheduled_at'], 0, 10)][] = $r;
        }

        $days = [];
        for ($d = $from; $d <= $to; $d = $d->modify('+1 day')) {
            $key = $d->format('Y-m-d');
            $days[] = [
                'date' => $d,
                'label' => JalaliCalendar::relativeDate($d),
                'rows' => $byDate[$key] ?? [],
            ];
        }

        return $days;
    }

    /** تاریخ نامعتبر در نوار آدرس نباید صفحه را بترکاند. */
    private function parseDate(string $value, DateTimeImmutable $fallback): DateTimeImmutable
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return $fallback;
        }

        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $parsed === false ? $fallback : $parsed;
    }
}
