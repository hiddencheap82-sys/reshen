<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Auth;
use App\Core\DB;
use App\Domain\Identity\PasswordService;
use PHPUnit\Framework\TestCase;

/**
 * ورود با رمز.
 *
 * چرا این تست هست: رمز تنها جایی در برنامه است که یک اشتباه، *سکوت*
 * نمی‌کند — در را باز می‌کند. هر ایراد اینجا یعنی کسی که نباید، وارد
 * شده؛ و برخلاف ایرادهای ظاهری، خودش را هیچ‌وقت نشان نمی‌دهد.
 */
final class PasswordLoginTest extends TestCase
{
    private const GOOD = 'ArayeshGah!1405';

    protected function setUp(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach (['login_attempts', 'salon_user', 'users'] as $t) {
            DB::statement("DELETE FROM `{$t}` WHERE 1");
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        Auth::logout();
    }

    private function makeUser(string $phone = '+989121234567', ?string $password = self::GOOD): int
    {
        $id = (int) DB::insert('users', ['phone' => $phone, 'name' => 'آزمون']);

        if ($password !== null) {
            PasswordService::set($id, $password);
        }

        return $id;
    }

    public function test_the_right_password_gets_in(): void
    {
        $id = $this->makeUser();
        $result = PasswordService::attempt('09121234567', self::GOOD);

        self::assertTrue($result['ok']);
        self::assertSame($id, $result['user_id']);
    }

    public function test_the_wrong_password_does_not(): void
    {
        $this->makeUser();

        self::assertFalse(PasswordService::attempt('09121234567', 'wrong-password')['ok']);
    }

    /** رمز هرگز به‌صورت متن ساده ذخیره نمی‌شود. */
    public function test_the_password_is_never_stored_in_plain_text(): void
    {
        $id = $this->makeUser();
        $row = DB::selectOne('SELECT password_hash FROM users WHERE id = ?', [$id]);

        self::assertStringNotContainsString(self::GOOD, (string) $row['password_hash']);
        self::assertTrue(password_verify(self::GOOD, (string) $row['password_hash']));
        // bcrypt، نه md5 یا sha1 که با جدول رنگین‌کمان شکسته می‌شوند.
        self::assertStringStartsWith('$2y$', (string) $row['password_hash']);
    }

    /**
     * کاربری که رمز ندارد، با رمز خالی وارد نمی‌شود.
     *
     * حالت خطرناکی است: اگر password_verify روی null یا رشتهٔ خالی
     * چیزی جز false برگرداند، هر مشتری‌ای که فقط با کد پیامکی ثبت شده
     * با یک فرم خالی وارد پنل می‌شد.
     */
    public function test_a_user_without_a_password_cannot_log_in_with_an_empty_one(): void
    {
        $this->makeUser('+989121234567', null);

        self::assertFalse(PasswordService::attempt('09121234567', '')['ok']);
        self::assertFalse(PasswordService::attempt('09121234567', 'anything')['ok']);
    }

    /**
     * پیام خطا نباید بگوید کدام شماره در سیستم هست.
     *
     * وگرنه صفحهٔ ورود تبدیل می‌شود به ابزارِ فهرست کردنِ شماره‌ها — و
     * این برنامه شمارهٔ مشتری‌های آرایشگاه‌ها را نگه می‌دارد.
     */
    public function test_the_error_does_not_reveal_whether_the_phone_exists(): void
    {
        $this->makeUser();

        $wrongPassword = PasswordService::attempt('09121234567', 'nope-not-it')['error'];
        $noSuchUser = PasswordService::attempt('09129999999', 'nope-not-it')['error'];

        self::assertSame($wrongPassword, $noSuchUser);
    }

    /** بعد از چند تلاش ناموفق، در بسته می‌شود. */
    public function test_brute_force_is_throttled(): void
    {
        $this->makeUser();

        $blocked = false;
        for ($i = 0; $i < 12; ++$i) {
            $result = PasswordService::attempt('09121234567', 'guess-' . $i);
            if (str_contains((string) ($result['error'] ?? ''), 'صبر')) {
                $blocked = true;
                break;
            }
        }

        self::assertTrue($blocked, 'بعد از تلاش‌های پیاپی هیچ محدودیتی اعمال نشد.');
    }

    /** و وقتی در بسته است، حتی رمز درست هم باز نمی‌کند. */
    public function test_throttling_also_blocks_the_correct_password(): void
    {
        $this->makeUser();

        for ($i = 0; $i < 10; ++$i) {
            PasswordService::attempt('09121234567', 'guess-' . $i);
        }

        self::assertFalse(PasswordService::attempt('09121234567', self::GOOD)['ok']);
    }

    /** شماره در هر شکلی نوشته شود، همان کاربر است. */
    public function test_the_phone_is_normalised_before_matching(): void
    {
        $id = $this->makeUser('+989121234567');

        foreach (['09121234567', '9121234567', '+989121234567', '۰۹۱۲۱۲۳۴۵۶۷'] as $written) {
            DB::statement('DELETE FROM login_attempts WHERE 1');
            $result = PasswordService::attempt($written, self::GOOD);

            self::assertTrue($result['ok'], "«{$written}» باید همان کاربر را پیدا کند");
            self::assertSame($id, $result['user_id']);
        }
    }

    public function test_a_weak_password_is_rejected(): void
    {
        foreach (['', 'kotah', '1234567', '12345678', 'password'] as $weak) {
            self::assertNotNull(
                PasswordService::reject($weak),
                "رمز «{$weak}» نباید پذیرفته شود"
            );
        }
    }

    public function test_a_reasonable_password_is_accepted(): void
    {
        foreach (['ArayeshGah!1405', 'قیچی و شانه و آینه', 'correct horse battery'] as $ok) {
            self::assertNull(PasswordService::reject($ok), "رمز «{$ok}» باید پذیرفته شود");
        }
    }

    /** رمز نباید خودِ شمارهٔ موبایل باشد — شماره روی هر فاکتور نوشته شده. */
    public function test_the_phone_number_itself_is_not_a_password(): void
    {
        self::assertNotNull(PasswordService::reject('09121234567', '09121234567'));
        self::assertNotNull(PasswordService::reject('9121234567', '09121234567'));
    }

    /** رمز فارسیِ هشت‌حرفی باید پذیرفته شود، نه با بایت شمرده شود. */
    public function test_a_persian_password_is_measured_in_characters(): void
    {
        self::assertNull(PasswordService::reject('گلمحمدیان'));
        self::assertNotNull(PasswordService::reject('قیچی'));
    }

    public function test_clearing_a_password_returns_the_user_to_sms_only(): void
    {
        $id = $this->makeUser();
        self::assertTrue(PasswordService::has($id));

        PasswordService::clear($id);

        self::assertFalse(PasswordService::has($id));
        self::assertFalse(PasswordService::attempt('09121234567', self::GOOD)['ok']);
    }

    /** ورود موفق نباید تلاش‌های بعدی را قفل کند. */
    public function test_successful_logins_do_not_count_toward_the_limit(): void
    {
        $this->makeUser();

        // بیش از سقفِ تلاش‌های ناموفق، ولی همه موفق — نباید قفل شود.
        for ($i = 0; $i < 12; ++$i) {
            self::assertTrue(PasswordService::attempt('09121234567', self::GOOD)['ok']);
        }
    }
}
