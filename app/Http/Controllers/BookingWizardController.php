<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\DB;
use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Domain\Booking\BookingService;
use App\Domain\Catalog\ServiceRepository;
use App\Domain\Identity\OtpService;
use App\Domain\Staff\StaffRepository;
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
            'salon' => $salon,
            'calendar' => JalaliCalendar::month($viewYear, $viewMonth, $dayStates),
            'minMonth' => ['year' => $todayJy, 'month' => $todayJm],
            'selectedDate' => $dateParam,
            'selectedDateLabel' => JalaliCalendar::relativeDate($date),
            'slots' => array_keys($slotsByTime),
        ]);
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

            $result = (new OtpService())->request($phone, 'booking');
            if (!$result['ok']) {
                return $this->withError($result['error'] ?? 'خطا در ارسال پیامک', '/s/' . $salon['slug'] . '/phone');
            }

            return $this->redirect('/s/' . $salon['slug'] . '/verify');
        }

        return $this->page('layouts.booking', 'booking.phone', [
            'title' => 'شمارهٔ موبایل',
            'salon' => $salon,
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

            try {
                $appointment = (new BookingService())->createBooking(
                    (int) $salon['id'],
                    $wizard['staff_id'] ?? null,
                    $wizard['service_ids'],
                    new DateTimeImmutable($wizard['date']),
                    $wizard['time'],
                    $wizard['phone'],
                    $wizard['name'] ?? null,
                );
            } catch (RuntimeException $e) {
                return $this->withError($e->getMessage(), '/s/' . $salon['slug'] . '/slots');
            }

            Session::forget($this->wizardKey($salon['slug']));

            return $this->redirect('/q/' . $appointment['public_token']);
        }

        return $this->page('layouts.booking', 'booking.verify', [
            'title' => 'تأیید کد',
            'salon' => $salon,
            'phone' => $wizard['phone'],
            'debugLine' => OtpService::devHint($wizard['phone']),
        ]);
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
