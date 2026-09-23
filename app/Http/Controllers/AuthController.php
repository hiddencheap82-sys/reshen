<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Domain\Identity\LoginLinkService;
use App\Domain\Identity\OtpService;
use App\Domain\Identity\PasswordService;
use App\Domain\Identity\UserRepository;
use App\Support\IranMobile;

final class AuthController extends Controller
{
    public function showLogin(Request $request): Response
    {
        if (Auth::check()) {
            return $this->redirect('/panel');
        }

        return $this->page('layouts.auth', 'auth.login', ['error' => Session::flash('error'), 'title' => 'ورود']);
    }

    /**
     * ورود با شماره و رمز.
     *
     * راهِ اصلیِ کارکنان است. کد پیامکی سر جایش می‌ماند ولی برای کسی
     * که روزی چند بار وارد می‌شود، هر بار صبر کردن پای گوشی یعنی
     * برنامه را باز نکردن.
     */
    public function loginWithPassword(Request $request): Response
    {
        $result = PasswordService::attempt(
            (string) $request->input('phone', ''),
            (string) $request->input('password', ''),
            $request->ip()
        );

        if (!$result['ok']) {
            // شماره را برمی‌گردانیم تا کاربر دوباره تایپش نکند؛ رمز را نه.
            Session::flash('_old', ['phone' => (string) $request->input('phone', '')]);

            return $this->withError($result['error'] ?? 'ورود ناموفق بود.', '/login');
        }

        Auth::login((int) $result['user_id']);

        return $this->afterLogin();
    }

    public function sendOtp(Request $request): Response
    {
        $raw = (string) $request->input('phone', '');
        $phone = IranMobile::tryParse($raw);

        if ($phone === null) {
            Session::flash('_old', ['phone' => $raw]);

            return $this->withError(
                $raw === ''
                    ? 'اول شمارهٔ موبایل‌تان را بنویسید، بعد این دکمه را بزنید.'
                    : 'شمارهٔ موبایل نامعتبر است.',
                '/login'
            );
        }

        $otp = new OtpService();
        $result = $otp->request($phone);

        if (!$result['ok']) {
            // شماره را نگه می‌داریم تا فرم خالی برنگردد.
            Session::flash('_old', ['phone' => $raw]);

            return $this->withError($result['error'] ?? 'خطا در ارسال کد', '/login');
        }

        Session::put('otp_phone', $phone->e164);

        return $this->redirect('/login/verify');
    }

    public function showVerify(Request $request): Response
    {
        $phone = Session::get('otp_phone');
        if ($phone === null) {
            return $this->redirect('/login');
        }

        return $this->page('layouts.auth', 'auth.verify', [
            'phone' => $phone,
            'error' => Session::flash('error'),
            'debugLine' => OtpService::devHint($phone),
            'title' => 'تأیید کد',
        ]);
    }

    public function verify(Request $request): Response
    {
        $phoneRaw = Session::get('otp_phone');
        if ($phoneRaw === null) {
            return $this->redirect('/login');
        }
        $phone = IranMobile::parse($phoneRaw);
        $code = (string) $request->input('code', '');

        $otp = new OtpService();
        $result = $otp->verify($phone, $code);

        if (!$result['ok']) {
            return $this->withError($result['error'] ?? 'کد نامعتبر است.', '/login/verify');
        }

        $users = new UserRepository();
        $user = $users->findOrCreate($phone);

        Auth::login((int) $user['id']);
        Session::forget('otp_phone');

        return $this->afterLogin();
    }

    /**
     * ورود با لینک یک‌بارمصرف (ت-۳۶).
     *
     * توکن فقط از روی سرور ساخته می‌شود (`php tools/login-link.php`).
     * هیچ مسیری برای **درخواست** لینک از وب وجود ندارد — وگرنه همان
     * چیزی می‌شد که کد پیامکی هست، منهای پیامک.
     */
    public function loginWithLink(Request $request): Response
    {
        $userId = (new LoginLinkService())->consume((string) $request->param('token'));

        if ($userId === null) {
            return $this->withError(
                'این لینک معتبر نیست یا قبلاً استفاده شده. یکی تازه بساز.',
                '/login'
            );
        }

        Auth::login($userId);

        return $this->afterLogin();
    }

    /**
     * مقصد پس از ورود موفق.
     *
     * یک عضویت → مستقیم به پنل. چند تا → صفحهٔ انتخاب سالن. هیچ‌کدام
     * → راه‌اندازی سالن تازه.
     */
    private function afterLogin(): Response
    {
        $memberships = Auth::memberships();

        if (count($memberships) === 1) {
            Auth::setSalon((int) $memberships[0]['salon_id']);

            return $this->redirect('/panel');
        }

        if (count($memberships) > 1) {
            return $this->redirect('/salons');
        }

        /*
         * مدیر کلی که عضو هیچ سالنی نیست، به پنل پلتفرم می‌رود نه به
         * فرم ساخت سالن.
         *
         * این دقیقاً حالتِ مدیری است که نصاب ساخته: او سالن ندارد و
         * قرار هم نیست داشته باشد — کارش نظارت بر همهٔ سالن‌هاست.
         * فرستادنش به «سالن تازه بساز» یعنی گفتن «تو جای اشتباهی
         * آمده‌ای» به کسی که صاحب کل سامانه است.
         */
        if (Auth::isPlatformAdmin()) {
            return $this->redirect('/platform');
        }

        return $this->redirect('/onboarding');
    }

    public function logout(Request $request): Response
    {
        Auth::logout();

        return $this->redirect('/login');
    }

    public function switchSalon(Request $request): Response
    {
        $salonId = (int) $request->param('id');
        $allowed = array_filter(Auth::memberships(), static fn ($m) => (int) $m['salon_id'] === $salonId);
        if (empty($allowed)) {
            return $this->withError('دسترسی به این سالن ندارید.', '/salons');
        }
        Auth::setSalon($salonId);

        return $this->redirect('/panel');
    }

    public function pickSalon(Request $request): Response
    {
        return $this->page('layouts.auth', 'auth.salons', ['memberships' => Auth::memberships(), 'title' => 'انتخاب سالن']);
    }

}
