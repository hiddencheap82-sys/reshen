<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Domain\Identity\PasswordService;

/**
 * حساب کاربری خودِ کاربر — نام و رمز.
 *
 * جدا از «تنظیمات سالن» است و باید هم باشد: تنظیمات سالن مالِ
 * کسب‌وکار است و فقط صاحب و مدیر می‌بینندش، این مالِ خودِ آدم است و
 * هر کسی که وارد شده باید بتواند رمز خودش را عوض کند — از پذیرش
 * گرفته تا مدیر کل.
 */
final class AccountController extends Controller
{
    public function show(Request $request): Response
    {
        $user = Auth::user();

        return $this->page('layouts.panel', 'panel.account', [
            'title' => 'حساب کاربری',
            'user' => $user,
            'hasPassword' => $user !== null && $user['password_hash'] !== null,
        ]);
    }

    public function updateName(Request $request): Response
    {
        $name = trim((string) $request->input('name', ''));

        if ($name === '') {
            return $this->withError('نام نمی‌تواند خالی باشد.', '/panel/account');
        }

        if (mb_strlen($name, 'UTF-8') > 120) {
            return $this->withError('نام خیلی بلند است.', '/panel/account');
        }

        DB::update('users', ['name' => $name], 'id = :id', ['id' => Auth::id()]);
        Auth::forgetUserCache();

        return $this->withSuccess('نام ذخیره شد.', '/panel/account');
    }

    /**
     * گذاشتن یا عوض کردن رمز.
     *
     * اگر کاربر رمز دارد، باید رمز فعلی را هم بزند. این جلوی سناریوی
     * «گوشی روی پیشخوان باز مانده» را می‌گیرد: کسی که رد می‌شود
     * نتواند در ده ثانیه رمز را عوض کند و حساب را بردارد.
     *
     * اگر رمز ندارد (با کد پیامکی وارد شده)، رمز فعلی‌ای هم در کار
     * نیست و همان ورودِ تأییدشده کافی است.
     */
    public function updatePassword(Request $request): Response
    {
        $userId = (int) Auth::id();
        $user = Auth::user();
        $current = (string) $request->input('current_password', '');
        $new = (string) $request->input('new_password', '');
        $confirm = (string) $request->input('new_password2', '');

        if ($user !== null && $user['password_hash'] !== null) {
            if (!password_verify($current, (string) $user['password_hash'])) {
                return $this->withError('رمز فعلی درست نیست.', '/panel/account');
            }
        }

        if ($new !== $confirm) {
            return $this->withError('دو رمز تازه یکی نیستند.', '/panel/account');
        }

        $weak = PasswordService::reject($new, (string) ($user['phone'] ?? ''));
        if ($weak !== null) {
            return $this->withError($weak, '/panel/account');
        }

        PasswordService::set($userId, $new);
        Auth::forgetUserCache();

        return $this->withSuccess('رمز عوض شد.', '/panel/account');
    }

    /**
     * برداشتن رمز — برگشت به ورود فقط با کد پیامکی.
     *
     * برای کسی است که رمز را روی دستگاه مشترکی گذاشته و پشیمان شده.
     * عمداً رمز فعلی را می‌خواهد، به همان دلیلِ بالا.
     */
    public function removePassword(Request $request): Response
    {
        $user = Auth::user();

        if ($user === null || $user['password_hash'] === null) {
            return $this->withError('رمزی ندارید که برداشته شود.', '/panel/account');
        }

        if (!password_verify((string) $request->input('current_password', ''), (string) $user['password_hash'])) {
            return $this->withError('رمز فعلی درست نیست.', '/panel/account');
        }

        PasswordService::clear((int) Auth::id());
        Auth::forgetUserCache();

        return $this->withSuccess('رمز برداشته شد. از این به بعد فقط با کد پیامکی وارد می‌شوید.', '/panel/account');
    }
}
