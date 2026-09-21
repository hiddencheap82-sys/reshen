<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Config;
use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Domain\Booking\BookingGuard;
use App\Domain\Booking\BookingService;
use App\Domain\Catalog\ServiceRepository;
use App\Domain\Identity\OtpService;
use App\Domain\Queue\QueueService;
use App\Domain\Staff\StaffRepository;
use App\Support\Clock;
use App\Support\IranMobile;
use App\Support\Jalali;
use App\Support\JalaliCalendar;
use DateTimeImmutable;
use RuntimeException;

/**
 * The public, no-install booking wizard (doc 5.5): link -> service ->
 * staff (or "any") -> free slot on a Jalali calendar -> phone -> OTP ->
 * confirm -> SMS with the "my appointment" link. Target: under 60 seconds,
 * five taps, nothing to install.
 */
final class BookingWizardController extends Controller
{
    public function landing(Request $request): Response
    {
        $salon = $this->salonOrFail((string) $request->param('slug'));
        if ($salon === null) {
            return Response::html('سالن یافت نشد.', 404);
        }

        $services = (new ServiceRepository())->all((int) $salon['id'], true);
        Session::forget($this->wizardKey($salon['slug']));

        return $this->page('layouts.booking', 'booking.landing', [
            'title' => $salon['name'],
            'salon' => $salon,
            'services' => $services,
            'step' => 1,
            'liveStatus' => $this->liveStatus((int) $salon['id']),
        ]);
    }

    /**
     * منوی خدمات — فهرست خواندنی، بدون شروع رزرو.
     *
     * چرا جدا از صفحهٔ اصلی: آنجا خدمت‌ها چک‌باکس‌اند و هدفشان شروع
     * رزرو است. ولی خیلی از مشتری‌ها اول فقط می‌خواهند بدانند «چی
     * دارید و چند؟» — مخصوصاً وقتی لینک را در اینستاگرام دیده‌اند.
     * با فهرستِ رزرو، سؤالِ قیمت جواب داده نمی‌شود مگر اینکه وارد
     * جریان رزرو شوند.
     *
     * لینکِ جدا یعنی سالن می‌تواند همین را در بیو بگذارد.
     */
    public function services(Request $request): Response
    {
        $salon = $this->salonOrFail((string) $request->param('slug'));
        if ($salon === null) {
            return Response::html('سالن یافت نشد.', 404);
        }

        return $this->page('layouts.booking', 'booking.services', [
            'title' => 'خدمات ' . $salon['name'],
            'salon' => $salon,
            'services' => (new ServiceRepository())->all((int) $salon['id'], true),
            'liveStatus' => $this->liveStatus((int) $salon['id']),
        ]);
    }

    public function chooseServices(Request $request): Response
    {
        $salon = $this->salonOrFail((string) $request->param('slug'));
        $serviceIds = array_values(array_filter(array_map('intval', (array) $request->input('service_ids', []))));
        if ($serviceIds === []) {
            return $this->withError('حداقل یک خدمت انتخاب کنید.', '/s/' . $salon['slug']);
        }

        $this->setWizard($salon['slug'], ['service_ids' => $serviceIds]);

        return $this->redirect('/s/' . $salon['slug'] . '/staff');
    }

    public function staffStep(Request $request): Response
    {
        $salon = $this->salonOrFail((string) $request->param('slug'));
        $wizard = $this->wizard($salon['slug']);
        if (empty($wizard['service_ids'])) {
            return $this->redirect('/s/' . $salon['slug']);
        }

        if ($request->method === 'POST') {
            $staffId = $request->input('staff_id');
            $this->setWizard($salon['slug'], ['staff_id' => $staffId !== '' ? (int) $staffId : null]);

            return $this->redirect('/s/' . $salon['slug'] . '/slots');
        }

        $staff = (new StaffRepository())->all((int) $salon['id'], true);

        return $this->page('layouts.booking', 'booking.staff', [
            'title' => 'انتخاب آرایشگر',
            'step' => 2,
            'salon' => $salon,
            'staff' => $staff,
        ]);
    }

    public function slotsStep(Request $request): Response
    {
        $salon = $this->salonOrFail((string) $request->param('slug'));
        $wizard = $this->wizard($salon['slug']);
        if (empty($wizard['service_ids'])) {
            return $this->redirect('/s/' . $salon['slug']);
        }

        if ($request->method === 'POST') {
            $date = (string) $request->input('date');
            $time = (string) $request->input('time');
            $this->setWizard($salon['slug'], ['date' => $date, 'time' => $time]);

            return $this->redirect('/s/' . $salon['slug'] . '/phone');
        }

        $dateParam = (string) $request->query('date', date('Y-m-d'));
        $date = new DateTimeImmutable($dateParam);

        $serviceRepo = new ServiceRepository();
        $duration = array_sum(array_map(
            static fn (int $id) => $serviceRepo->find((int) $salon['id'], $id)['duration_minutes'] ?? 30,
            $wizard['service_ids']
        ));

        $booking = new BookingService();
        $slotsByTime = $booking->freeSlots((int) $salon['id'], $wizard['staff_id'] ?? null, $date, $duration);

        /*
         * ماهی که تقویم نشان می‌دهد. پیش‌فرض ماهِ تاریخ انتخاب‌شده است،
         * ولی کاربر می‌تواند با دکمه‌های قبل/بعد جابه‌جا شود — پس ماه از
         * پارامتر آدرس هم خوانده می‌شود.
         */
        [$todayJy, $todayJm] = Jalali::fromDateTime(new DateTimeImmutable('today'));
        [$dateJy, $dateJm] = Jalali::fromDateTime($date);

        $viewYear = (int) $request->query('jy', (string) $dateJy);
        $viewMonth = (int) $request->query('jm', (string) $dateJm);
        if ($viewMonth < 1 || $viewMonth > 12) {
            $viewMonth = $dateJm;
            $viewYear = $dateJy;
        }

        /*
         * وضعیت هر روزِ ماه: آیا اصلاً وقتی آزاد دارد؟
         *
         * بدون این، تقویم روزهایی را قابل انتخاب نشان می‌دهد که سالن
         * تعطیل است یا همهٔ نوبت‌هایش پر شده — و مشتری بعد از دو کلیک
         * به صفحهٔ خالی می‌رسد. بهتر است همان اول ببیند کدام روزها باز است.
         */
        $dayStates = $this->monthAvailability(
            (int) $salon['id'],
            $wizard['staff_id'] ?? null,
            $duration,
            $viewYear,
            $viewMonth
        );

        return $this->page('layouts.booking', 'booking.slots', [
            'title' => 'انتخاب زمان',
            'step' => 3,
            'salon' => $salon,
            'calendar' => JalaliCalendar::month($viewYear, $viewMonth, $dayStates),
            'minMonth' => ['year' => $todayJy, 'month' => $todayJm],
            'selectedDate' => $dateParam,
            'selectedDateLabel' => JalaliCalendar::relativeDate($date),
            'slots' => array_keys($slotsByTime),
        ]);
    }

    /**
     * وضعیت لحظه‌ای سالن برای نمایش در صفحهٔ اول.
     *
     * این جواب سؤالی است که مشتری واقعاً در ذهن دارد: «الان برم یا
     * شلوغه؟» — و چیزی است که رقبا نمی‌توانند داشته باشند، چون دادهٔ
     * لحظه‌ای صف را ندارند.
     *
     * @return array{open:bool,waiting:int,freeNow:int,chairs:int,waitLabel:string}
     */
    private function liveStatus(int $salonId): array
    {
        $snapshot = (new QueueService())->salonSnapshot($salonId);

        $chairs = count($snapshot);
        $waiting = 0;
        $freeNow = 0;
        $soonest = null;

        foreach ($snapshot as $chair) {
            $queue = $chair['queue'] ?? [];
            $waiting += count(array_filter(
                $queue,
                static fn (array $a): bool => ($a['status'] ?? '') === 'queued'
            ));

            if ($queue === []) {
                $freeNow++;
                continue;
            }

            /*
             * سؤال مشتری این است: «اگر الان بیایم، کِی روی صندلی
             * می‌نشینم؟» — نه اینکه نفر آخرِ صف کِی شروع می‌کند.
             *
             * پس باید زمانِ **پایانِ** کار نفر آخر را حساب کرد، نه
             * زمان شروعش. (اولین نسخه شروع را گرفت و نتیجه «۰ دقیقه
             * انتظار» شد درحالی‌که سه نفر در صف بودند.)
             */
            $last = end($queue);
            $startsAt = $last['eta']['start_p50'] ?? null;
            $duration = (int) ($last['eta']['expected_p50'] ?? 30);

            if ($startsAt instanceof DateTimeImmutable) {
                $freeAt = $startsAt->modify('+' . $duration . ' minutes');
                if ($soonest === null || $freeAt < $soonest) {
                    $soonest = $freeAt;
                }
            }
        }

        $label = 'تخمین انتظار در دسترس نیست';
        if ($soonest !== null) {
            $minutes = (int) round(($soonest->getTimestamp() - time()) / 60);
            $label = $minutes <= 5
                ? 'تقریباً بدون انتظار'
                : 'حدود ' . Jalali::toPersianDigits((string) $minutes) . ' دقیقه انتظار';
        }

        return [
            'open' => $this->isOpenNow($salonId),
            'waiting' => $waiting,
            'freeNow' => $freeNow,
            'chairs' => $chairs,
            'waitLabel' => $label,
        ];
    }

    /** آیا الان ساعت کاری است؟ */
    private function isOpenNow(int $salonId): bool
    {
        $now = new DateTimeImmutable();
        $weekday = Jalali::weekday($now);

        $row = DB::selectOne(
            'SELECT 1 AS ok FROM working_hours
              WHERE salon_id = ? AND weekday = ? AND is_closed = 0
                AND ? BETWEEN opens_at AND closes_at
              LIMIT 1',
            [$salonId, $weekday, $now->format('H:i:s')]
        );

        return $row !== null;
    }

    /**
     * برای هر روز ماه، بگو وقت آزاد دارد یا نه.
     *
     * @return array<string,array{available:bool,label:string}>
     */
    private function monthAvailability(
        int $salonId,
        ?int $staffId,
        int $duration,
        int $jy,
        int $jm
    ): array {
        $today = new DateTimeImmutable('today');
        $horizon = $today->modify('+' . (int) Config::get('reshen.booking.max_days_ahead', 30) . ' days');
        $booking = new BookingService();

        $states = [];
        $daysInMonth = Jalali::daysInJalaliMonth($jy, $jm);

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = Jalali::toDateTime($jy, $jm, $day);
            $key = $date->format('Y-m-d');

            if ($date < $today) {
                $states[$key] = ['available' => false, 'label' => 'گذشته'];
                continue;
            }

            if ($date > $horizon) {
                $states[$key] = ['available' => false, 'label' => 'هنوز باز نشده'];
                continue;
            }

            $free = $booking->freeSlots($salonId, $staffId, $date, $duration);
            $states[$key] = $free === []
                ? ['available' => false, 'label' => 'بدون وقت آزاد']
                : ['available' => true, 'label' => ''];
        }

        return $states;
    }

    public function phoneStep(Request $request): Response
    {
        $salon = $this->salonOrFail((string) $request->param('slug'));
        $wizard = $this->wizard($salon['slug']);
        if (empty($wizard['date']) || empty($wizard['time'])) {
            return $this->redirect('/s/' . $salon['slug']);
        }

        if ($request->method === 'POST') {
            $phone = IranMobile::tryParse((string) $request->input('phone', ''));
            if ($phone === null) {
                return $this->withError('شمارهٔ موبایل نامعتبر است.', '/s/' . $salon['slug'] . '/phone');
            }
            $name = trim((string) $request->input('name', ''));
            $this->setWizard($salon['slug'], ['phone' => $phone->e164, 'name' => $name ?: null]);

            /*
             * پیش‌فرض: بدون کد تأیید، نوبت همین‌جا ثبت می‌شود (ت-۳۵).
             *
             * هر گام اضافه بخشی از مشتری‌ها را می‌ریزد، و کد تأیید یعنی
             * از برنامه بیرون برو و برگرد. جای آن را BookingGuard
             * می‌گیرد.
             */
            if (!Config::get('reshen.booking.verify_phone', false)) {
                return $this->finishBooking($salon, $request);
            }

            $result = (new OtpService())->request($phone, 'booking');
            if (!$result['ok']) {
                return $this->withError($result['error'] ?? 'خطا در ارسال پیامک', '/s/' . $salon['slug'] . '/phone');
            }

            return $this->redirect('/s/' . $salon['slug'] . '/verify');
        }

        return $this->page('layouts.booking', 'booking.phone', [
            'title' => 'شمارهٔ موبایل',
            'step' => 4,
            'salon' => $salon,
            'needsVerification' => (bool) Config::get('reshen.booking.verify_phone', false),
            'summary' => $this->wizardSummary($salon, $wizard),
        ]);
    }

    public function verifyStep(Request $request): Response
    {
        $salon = $this->salonOrFail((string) $request->param('slug'));
        $wizard = $this->wizard($salon['slug']);
        if (empty($wizard['phone'])) {
            return $this->redirect('/s/' . $salon['slug']);
        }

        if ($request->method === 'POST') {
            $code = (string) $request->input('code', '');
            $phone = IranMobile::parse($wizard['phone']);
            $result = (new OtpService())->verify($phone, $code, 'booking');
            if (!$result['ok']) {
                return $this->withError($result['error'] ?? 'کد نامعتبر است.', '/s/' . $salon['slug'] . '/verify');
            }

            return $this->finishBooking($salon, $request);
        }

        return $this->page('layouts.booking', 'booking.verify', [
            'title' => 'تأیید کد',
            'salon' => $salon,
            'phone' => $wizard['phone'],
            'debugLine' => OtpService::devHint($wizard['phone']),
        ]);
    }

    /**
     * ثبت نهایی نوبت — مسیر مشترکِ با و بدون کد تأیید.
     *
     * هر دو راه به اینجا می‌رسند تا منطقِ ساخت نوبت یک جا بماند. اگر
     * دو نسخه می‌داشت، روزی یکی‌شان اصلاح می‌شد و دیگری نه.
     */
    /**
     * خلاصهٔ انتخاب‌ها برای آخرین گام.
     *
     * مشتری پیش از دادن شماره باید ببیند چه چیزی را تأیید می‌کند. سه
     * صفحه قبل خدمت را انتخاب کرده و یادش نیست — و اگر اشتباه باشد،
     * پس از ثبت می‌فهمد که دیرِ کار است.
     *
     * @return array<string,string>
     */
    private function wizardSummary(array $salon, array $wizard): array
    {
        if (empty($wizard['date']) || empty($wizard['time'])) {
            return [];
        }

        $summary = [];

        if (!empty($wizard['service_ids'])) {
            $repo = new ServiceRepository();
            $names = [];
            $total = 0;
            foreach ($wizard['service_ids'] as $id) {
                $service = $repo->find((int) $salon['id'], (int) $id);
                if ($service !== null) {
                    $names[] = $service['name'];
                    $total += (int) $service['price'];
                }
            }
            if ($names !== []) {
                $summary['خدمت'] = implode('، ', $names);
                $summary['هزینه'] = toman($total);
            }
        }

        if (!empty($wizard['staff_id'])) {
            $staff = DB::selectOne('SELECT name FROM staff WHERE id = ? AND salon_id = ?', [$wizard['staff_id'], $salon['id']]);
            if ($staff !== null) {
                $summary['آرایشگر'] = $staff['name'];
            }
        }

        $date = new DateTimeImmutable($wizard['date']);
        $summary['زمان'] = JalaliCalendar::relativeDate($date) . '، ساعت ' . Clock::hm($wizard['time']);

        return $summary;
    }

    private function finishBooking(array $salon, Request $request): Response
    {
        $slug = $salon['slug'];
        $wizard = $this->wizard($slug);

        if (empty($wizard['phone']) || empty($wizard['date']) || empty($wizard['time'])) {
            return $this->redirect('/s/' . $slug);
        }

        // حفاظ ضدِ سوءاستفاده — جای کاری که کد تأیید می‌کرد
        $guard = (new BookingGuard())->check((int) $salon['id'], $wizard['phone'], $request->ip());
        if (!$guard['ok']) {
            return $this->withError($guard['error'], '/s/' . $slug . '/phone');
        }

        try {
            $appointment = (new BookingService())->createBooking(
                (int) $salon['id'],
                $wizard['staff_id'] ?? null,
                $wizard['service_ids'],
                new DateTimeImmutable($wizard['date']),
                $wizard['time'],
                $wizard['phone'],
                $wizard['name'] ?? null,
                $request->ip(),
            );
        } catch (RuntimeException $e) {
            // سانس بین انتخاب و ثبت پر شده — به مرحلهٔ زمان برگرد،
            // نه به اول، تا انتخاب خدمت از دست نرود.
            return $this->withError($e->getMessage(), '/s/' . $slug . '/slots');
        }

        Session::forget($this->wizardKey($slug));

        return $this->redirect('/q/' . $appointment['public_token']);
    }

    private function salonOrFail(string $slug): ?array
    {
        return DB::selectOne('SELECT * FROM salons WHERE slug = ? AND is_active = 1', [$slug]);
    }

    private function wizardKey(string $slug): string
    {
        return 'booking_' . $slug;
    }

    private function wizard(string $slug): array
    {
        return Session::get($this->wizardKey($slug), []);
    }

    private function setWizard(string $slug, array $data): void
    {
        Session::put($this->wizardKey($slug), array_merge($this->wizard($slug), $data));
    }
}
