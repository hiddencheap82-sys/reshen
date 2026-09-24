<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\DB;
use App\Core\Request;
use App\Http\Controllers\PaymentController;
use App\Support\Digits;
use PHPUnit\Framework\TestCase;

/**
 * ارقامی که ایرانی‌ها واقعاً تایپ می‌کنند.
 *
 * باگی که این تست قفلش می‌کند: صفحه‌کلید فارسیِ گوشی ارقام فارسی
 * می‌نویسد. `<input type="number">` آن‌ها را بی‌صدا دور می‌ریخت (فیلد
 * خالی ارسال می‌شد، بدون هیچ خطایی) و در PHP ‎(int) "۲۰۰۰۰۰"‎ صفر است.
 * نتیجه: آرایشگری که مبلغ را فارسی می‌زد، هر پرداخت را «۰ تومان» ثبت
 * می‌کرد — و گزارش فروش بی‌صدا غلط می‌شد.
 */
final class PersianDigitsTest extends TestCase
{
    // ─── Digits ──────────────────────────────────────────────────────

    public function test_persian_arabic_and_latin_digits_all_read_the_same(): void
    {
        self::assertSame(200000, Digits::toInt('۲۰۰۰۰۰'));
        self::assertSame(200000, Digits::toInt('٢٠٠٠٠٠'));
        self::assertSame(200000, Digits::toInt('200000'));
    }

    /** مردم مبلغ را همان‌طور می‌نویسند که روی صفحه می‌بینند: با جداکننده. */
    public function test_thousands_separators_are_accepted(): void
    {
        self::assertSame(250000, Digits::toInt('۲۵۰٬۰۰۰'));
        self::assertSame(250000, Digits::toInt('250,000'));
        self::assertSame(250000, Digits::toInt(' ۲۵۰ ۰۰۰ '));
    }

    public function test_empty_or_non_numeric_is_null_not_zero(): void
    {
        self::assertNull(Digits::toInt(''));
        self::assertNull(Digits::toInt('   '));
        self::assertNull(Digits::toInt('دویست'));
        self::assertNull(Digits::toInt(null));
    }

    public function test_persian_decimal_separator_for_commission(): void
    {
        self::assertSame(42.5, Digits::toFloat('۴۲٫۵'));
        self::assertSame(40.0, Digits::toFloat('۴۰'));
        self::assertNull(Digits::toFloat(''));
    }

    /** متنِ آزاد دست نمی‌خورد — فقط فیلدهای عددی از این راه می‌روند. */
    public function test_to_latin_leaves_letters_alone(): void
    {
        self::assertSame('ساعت 5 میام', Digits::toLatin('ساعت ۵ میام'));
    }

    // ─── Request ─────────────────────────────────────────────────────

    public function test_request_integer_reads_persian_digits(): void
    {
        $_POST = ['amount_toman' => '۲۵۰۰۰۰'];

        self::assertSame(250000, (new Request())->integer('amount_toman'));
    }

    public function test_request_integer_falls_back_to_default_when_empty(): void
    {
        $_POST = ['seats' => ''];

        self::assertSame(1, (new Request())->integer('seats', 1));
    }

    // ─── پرداختِ واقعی ───────────────────────────────────────────────

    /**
     * همان کاری که آرایشگر پشت پیشخوان می‌کند: مبلغ را با صفحه‌کلید
     * فارسی می‌زند و «ثبت» را. باید همان مبلغ ثبت شود، نه صفر.
     */
    public function test_a_payment_typed_in_persian_digits_is_recorded_in_full(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach (['payments', 'appointments', 'customers', 'salons'] as $t) {
            DB::statement("DELETE FROM `{$t}` WHERE 1");
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $salonId = (int) DB::insert('salons', [
            'slug' => 'pay-' . bin2hex(random_bytes(4)), 'name' => 'سالن', 'is_active' => 1, 'seats' => 1,
        ]);
        $customerId = (int) DB::insert('customers', [
            'salon_id' => $salonId, 'name' => 'مشتری', 'phone' => '+989120000001',
        ]);
        $apptId = (int) DB::insert('appointments', [
            'salon_id' => $salonId, 'public_token' => bin2hex(random_bytes(6)),
            'customer_id' => $customerId, 'kind' => 'walkin', 'status' => 'completed',
        ]);

        $_SESSION = ['user_id' => 1, 'salon_id' => $salonId];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['amount_toman' => '۲۵۰٬۰۰۰', 'tip_toman' => '۲۰۰۰۰', 'method' => 'cash'];

        $request = new Request();
        $request->routeParams = ['id' => (string) $apptId];
        (new PaymentController())->store($request);

        $row = DB::selectOne('SELECT amount, tip_amount FROM payments WHERE appointment_id = ?', [$apptId]);

        // مبلغ به ریال ذخیره می‌شود: ۲۵۰٬۰۰۰ تومان = ۲٬۵۰۰٬۰۰۰ ریال
        self::assertSame(2500000, (int) $row['amount']);
        self::assertSame(200000, (int) $row['tip_amount']);

        $_SESSION = [];
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    // ─── قفل روی ویوها ───────────────────────────────────────────────

    /**
     * `type="number"` برنمی‌گردد.
     *
     * هر فیلد عددیِ تازه‌ای که با type=number ساخته شود، همین باگ را
     * دوباره می‌سازد — بی‌صدا، و فقط برای کاربرِ صفحه‌کلید فارسی، یعنی
     * تقریباً همه. ورودیِ عددی: type="text" با inputmode="numeric".
     */
    public function test_no_view_uses_type_number(): void
    {
        $offenders = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(BASE_PATH . '/resources/views'));

        foreach ($it as $file) {
            if ($file->isFile() && $file->getExtension() === 'php'
                && preg_match('/type\s*=\s*["\']number["\']/i', (string) file_get_contents($file->getPathname())) === 1) {
                $offenders[] = str_replace(BASE_PATH . '/resources/views/', '', $file->getPathname());
            }
        }

        self::assertSame([], $offenders, 'type="number" ارقام فارسی را بی‌صدا حذف می‌کند.');
    }
}
