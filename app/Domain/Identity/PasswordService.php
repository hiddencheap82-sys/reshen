<?php

declare(strict_types=1);

namespace App\Domain\Identity;

use App\Core\DB;
use App\Support\IranMobile;

/**
 * رمز عبور کارکنان.
 *
 * ورود اصلیِ برنامه همچنان کد پیامکی است و برای مشتری هم همان می‌ماند.
 * رمز برای کسی است که *هر روز* وارد می‌شود — صاحب سالن، پذیرش، مدیر
 * کل — و برای او کد پیامکی یعنی صبر کردن پای گوشی، چند بار در روز.
 *
 * و یک دلیل عملی‌تر: روی هاست تازه هنوز پیامک تنظیم نشده. بدون رمز،
 * تنها راه ورود خواندن storage/logs/sms.log با File Manager است.
 *
 * ── چه چیزی اینجا نیست و چرا ──
 *
 * «فراموشی رمز» با ایمیل نداریم، چون ایمیل نداریم. اگر کسی رمزش را
 * فراموش کند، با کد پیامکی وارد می‌شود و رمز تازه می‌گذارد — همان
 * مسیر، بدون لایهٔ اضافه. مدیر کل هم می‌تواند رمز هر کسی را از پنل
 * بازنشانی کند.
 */
final class PasswordService
{
    /**
     * کوتاه‌ترین رمز مجاز.
     *
     * هشت نویسه، نه دوازده و نه با اجبارِ «یک عدد و یک نویسهٔ خاص».
     * آن قاعده‌ها در عمل «Salon@1234» می‌سازند که هم ضعیف است هم
     * فراموش‌شدنی. NIST هم از ۲۰۱۷ همین را می‌گوید: طول مهم است،
     * ترکیبِ اجباری نه.
     */
    public const MIN_LENGTH = 8;

    /** بیشترین تلاش ناموفق برای یک شماره، در بازهٔ زیر. */
    private const MAX_ATTEMPTS = 8;

    /** پنجرهٔ شمارش تلاش‌ها، به دقیقه. */
    private const WINDOW_MINUTES = 15;

    /**
     * رمزهایی که هیچ‌وقت نباید پذیرفته شوند.
     *
     * فهرست کوتاه و عمداً محلی است: این‌ها چیزهایی‌اند که یک آرایشگر
     * ایرانی واقعاً می‌گذارد، نه ۱۰ هزار رمزِ فهرستِ انگلیسی.
     */
    private const BANNED = [
        '12345678', '123456789', '1234567890', 'password', 'qwertyui',
        'reshen', 'reshen123', '11111111', '00000000', 'salon123',
        'admin123', 'iloveyou', 'abcd1234', '87654321',
    ];

    /**
     * رمز قابل قبول است؟
     *
     * @return string|null متن خطا، یا null اگر مشکلی نیست
     */
    public static function reject(string $password, ?string $phone = null): ?string
    {
        // طول را با نویسه می‌سنجیم نه بایت: رمز فارسی هشت‌حرفی نباید
        // «۱۶ نویسه» شمرده شود و از سد رد شود.
        $length = mb_strlen($password, 'UTF-8');

        if ($length < self::MIN_LENGTH) {
            return 'رمز باید دست‌کم ' . self::MIN_LENGTH . ' نویسه باشد.';
        }

        if ($length > 200) {
            return 'رمز خیلی بلند است.';
        }

        if (trim($password) === '') {
            return 'رمز نمی‌تواند فقط فاصله باشد.';
        }

        if (in_array(mb_strtolower($password, 'UTF-8'), self::BANNED, true)) {
            return 'این رمز خیلی رایج است. چیز دیگری بگذارید.';
        }

        /*
         * رمز نباید خودِ شمارهٔ موبایل باشد.
         *
         * شمارهٔ موبایل نامِ کاربری است و روی هر فاکتور و هر پیامک
         * نوشته شده. اگر رمز هم همان باشد، رمزی در کار نیست.
         */
        if ($phone !== null) {
            $digits = preg_replace('/\D/', '', $phone) ?? '';
            $inPassword = preg_replace('/\D/', '', $password) ?? '';

            if ($digits !== '' && $inPassword !== '' && str_contains($digits, $inPassword)) {
                return 'رمز نباید خودِ شمارهٔ موبایل باشد.';
            }
        }

        return null;
    }

    public static function hash(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    /** رمز را روی کاربر می‌نشاند. */
    public static function set(int $userId, string $password): void
    {
        DB::update('users', [
            'password_hash' => self::hash($password),
            'password_updated_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $userId]);
    }

    public static function clear(int $userId): void
    {
        DB::update('users', [
            'password_hash' => null,
            'password_updated_at' => null,
        ], 'id = :id', ['id' => $userId]);
    }

    /**
     * ورود با شماره و رمز.
     *
     * @return array{ok:bool,user_id?:int,error?:string}
     */
    public static function attempt(string $rawPhone, string $password, ?string $ip = null): array
    {
        $phone = IranMobile::tryParse($rawPhone);

        if ($phone === null) {
            return ['ok' => false, 'error' => 'شمارهٔ موبایل نامعتبر است.'];
        }

        $blocked = self::tooManyAttempts($phone->e164, $ip);
        if ($blocked !== null) {
            return ['ok' => false, 'error' => $blocked];
        }

        $user = DB::selectOne(
            'SELECT id, password_hash FROM users WHERE phone = ?',
            [$phone->e164]
        );

        /*
         * یک پیام برای هر سه حالتِ «کاربر نیست»، «رمز ندارد» و «رمز
         * غلط است».
         *
         * اگر تفکیک کنیم، این صفحه تبدیل می‌شود به ابزارِ فهمیدنِ
         * اینکه چه شماره‌هایی در سیستم هستند — و در برنامه‌ای که
         * شمارهٔ موبایلِ مشتری‌های یک آرایشگاه را نگه می‌دارد، همان
         * خودش یک نشتی است.
         */
        $wrong = 'شماره یا رمز درست نیست.';

        if ($user === null || $user['password_hash'] === null) {
            /*
             * یک hash الکی را هم می‌سنجیم تا زمانِ پاسخ با حالتِ
             * «کاربر هست ولی رمز غلط» یکی باشد. بدون این، اختلافِ
             * چند میلی‌ثانیه‌ای می‌گوید کدام شماره در سیستم هست.
             */
            password_verify($password, '$2y$12$usesomesillystringfooosomethingxxxxxxxxxxxxxxxxxxxxxxxxxxxxx');
            self::record($phone->e164, $ip, false);

            return ['ok' => false, 'error' => $wrong];
        }

        if (!password_verify($password, (string) $user['password_hash'])) {
            self::record($phone->e164, $ip, false);

            return ['ok' => false, 'error' => $wrong];
        }

        // الگوریتم پیش‌فرض PHP روزی عوض می‌شود؛ آن روز رمز بی‌سروصدا
        // با الگوریتم تازه دوباره hash می‌شود، بی‌آنکه کاربر بفهمد.
        if (password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT)) {
            self::set((int) $user['id'], $password);
        }

        self::record($phone->e164, $ip, true);

        return ['ok' => true, 'user_id' => (int) $user['id']];
    }

    /** آیا این شماره یا IP بیش از حد تلاش کرده؟ */
    private static function tooManyAttempts(string $phone, ?string $ip): ?string
    {
        $since = date('Y-m-d H:i:s', time() - self::WINDOW_MINUTES * 60);

        $byPhone = DB::selectOne(
            'SELECT COUNT(*) AS c FROM login_attempts
              WHERE phone = ? AND succeeded = 0 AND created_at > ?',
            [$phone, $since]
        );

        if ((int) ($byPhone['c'] ?? 0) >= self::MAX_ATTEMPTS) {
            return 'تلاش‌های ناموفق زیاد بود. ' . self::WINDOW_MINUTES
                . ' دقیقه صبر کنید، یا با کد پیامکی وارد شوید.';
        }

        /*
         * سقف IP بازتر است، چون پشت NAT و اینترنت موبایل ایران چند نفر
         * یک IP دارند. با سقفِ تنگ، یکی دیگر می‌توانست ناخواسته در را
         * روی همهٔ کارکنانِ یک سالن ببندد.
         */
        if ($ip !== null && $ip !== '') {
            $byIp = DB::selectOne(
                'SELECT COUNT(*) AS c FROM login_attempts
                  WHERE ip_address = ? AND succeeded = 0 AND created_at > ?',
                [$ip, $since]
            );

            if ((int) ($byIp['c'] ?? 0) >= self::MAX_ATTEMPTS * 5) {
                return 'تلاش‌های ناموفق زیاد بود. کمی بعد دوباره امتحان کنید.';
            }
        }

        return null;
    }

    private static function record(string $phone, ?string $ip, bool $ok): void
    {
        DB::insert('login_attempts', [
            'phone' => $phone,
            'ip_address' => $ip !== null && $ip !== '' ? substr($ip, 0, 45) : null,
            'succeeded' => $ok ? 1 : 0,
        ]);
    }

    /** آیا این کاربر رمز دارد؟ */
    public static function has(int $userId): bool
    {
        $row = DB::selectOne('SELECT password_hash FROM users WHERE id = ?', [$userId]);

        return $row !== null && $row['password_hash'] !== null;
    }

    /**
     * پاک کردن تلاش‌های قدیمی.
     *
     * از کرون صدا زده می‌شود. بدون این، جدول تا ابد بزرگ می‌شود و روی
     * هاست اشتراکی سهمیهٔ دیتابیس محدود است.
     */
    public static function pruneAttempts(int $days = 30): void
    {
        DB::statement(
            'DELETE FROM login_attempts WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)',
            [$days]
        );
    }
}
