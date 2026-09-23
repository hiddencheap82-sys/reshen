<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Domain\Customer\CustomerAuth;

/**
 * صفحهٔ نخست — دروازهٔ دسترسی.
 *
 * پیش از این `/` مستقیم به `/login` می‌رفت. برای صاحب سالن درست بود،
 * ولی برای بقیه نه: مشتری‌ای که لینک سالن را گم کرده بود، به صفحهٔ
 * ورودی می‌رسید که کد پیامکی می‌خواست و هیچ ربطی به او نداشت. مدیر
 * پلتفرم هم راهی به پنل خودش نداشت مگر اینکه نشانی را از بر باشد.
 *
 * حالا این صفحه می‌پرسد «تو کی هستی؟» و همان‌جا راه هرکدام را نشان
 * می‌دهد. عمداً صفحهٔ بازاریابی نیست — نه ادعا دارد نه قیمت. فقط
 * دری است که پشتش چند در دیگر هست.
 *
 * اگر کاربر واردشده باشد، مسیرِ خودش را بالای صفحه می‌بیند تا یک کلیک
 * کمتر بخورد.
 */
final class LandingController extends Controller
{
    public function index(Request $request): Response
    {
        $userId = Auth::id();
        $customerPhone = CustomerAuth::phone();

        return $this->page('layouts.plain', 'landing.index', [
            'title' => 'رشن — نوبت‌دهی آرایشگاه',
            'isLoggedIn' => $userId !== null,
            'isPlatformAdmin' => Auth::isPlatformAdmin(),
            'salons' => $userId === null ? [] : Auth::memberships(),
            'customerPhone' => $customerPhone,
            'salonCount' => $this->activeSalonCount(),
        ]);
    }

    /**
     * چند سالن فعال روی این نصب هست.
     *
     * برای نمایش نیست، برای تصمیم است: روی نصبی که هنوز هیچ سالنی
     * ندارد، صفحه به‌جای «سالنت را پیدا کن»، «اولین سالن را بساز» را
     * نشان می‌دهد.
     */
    private function activeSalonCount(): int
    {
        try {
            $row = DB::selectOne('SELECT COUNT(*) AS c FROM salons WHERE is_active = 1');

            return (int) ($row['c'] ?? 0);
        } catch (\Throwable) {
            // دیتابیس هنوز آماده نیست (پیش از نصب). صفحه باید باز شود.
            return 0;
        }
    }
}
