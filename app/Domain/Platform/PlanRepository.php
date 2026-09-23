<?php

declare(strict_types=1);

namespace App\Domain\Platform;

use App\Core\DB;

/**
 * پلن‌های پلتفرم — چیزی که سالن‌ها بابتش پول می‌دهند.
 *
 * تفاوتش با `subscription_plans` که اسمش شبیه است: آن یکی اشتراکی
 * است که *سالن به مشتری‌هایش* می‌فروشد (مثلاً «ماهی دو اصلاح»). این
 * یکی اشتراکی است که *ما به سالن* می‌فروشیم. قاطی شدنشان یعنی
 * صورتحساب اشتباه.
 */
final class PlanRepository
{
    /** @return array<int,array> همهٔ پلن‌ها به ترتیب نمایش */
    public function all(): array
    {
        return DB::select('SELECT * FROM plans ORDER BY sort_order, monthly_price');
    }

    public function find(string $code): ?array
    {
        return DB::selectOne('SELECT * FROM plans WHERE code = ?', [$code]);
    }

    /**
     * قیمت ماهانهٔ یک سالن با تعداد صندلی‌اش.
     *
     * صندلی اضافه جدا حساب می‌شود: پلن «سالن» تا سه صندلی دارد و از
     * چهارمی به بعد ماهی ۲۵۰ هزار تومان. بدون این حساب، سالنی که پنج
     * صندلی دارد همان پول سه صندلی را می‌دهد.
     */
    public function monthlyPriceFor(string $planCode, int $seats): int
    {
        $plan = $this->find($planCode);
        if ($plan === null) {
            return 0;
        }

        $price = (int) $plan['monthly_price'];
        $included = $plan['max_seats'] === null ? null : (int) $plan['max_seats'];
        $extraPrice = $plan['extra_seat_price'] === null ? 0 : (int) $plan['extra_seat_price'];

        if ($included !== null && $extraPrice > 0 && $seats > $included) {
            $price += ($seats - $included) * $extraPrice;
        }

        return $price;
    }

    /**
     * آیا این پلن اجازهٔ این تعداد صندلی را می‌دهد؟
     *
     * پلن «تک‌صندلی» صندلی اضافه ندارد، پس دو صندلی رویش ممکن نیست —
     * نه اینکه گران‌تر شود، اصلاً نمی‌شود. پنل مدیریت باید پیش از
     * ذخیره جلویش را بگیرد.
     */
    public function allowsSeats(string $planCode, int $seats): bool
    {
        $plan = $this->find($planCode);
        if ($plan === null) {
            return false;
        }
        if ($plan['max_seats'] === null) {
            return true;
        }

        $included = (int) $plan['max_seats'];
        if ($seats <= $included) {
            return true;
        }

        return $plan['extra_seat_price'] !== null && (int) $plan['extra_seat_price'] > 0;
    }

    public function update(string $code, array $fields): void
    {
        $allowed = array_intersect_key($fields, array_flip([
            'name', 'monthly_price', 'max_seats', 'extra_seat_price', 'sms_gift_monthly',
            'has_waitlist', 'has_payout', 'has_loyalty', 'has_advanced_reports',
            'has_multi_branch', 'sort_order',
        ]));

        if ($allowed === []) {
            return;
        }

        DB::update('plans', $allowed, 'code = :code', ['code' => $code]);
    }
}
