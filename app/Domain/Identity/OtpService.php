<?php

declare(strict_types=1);

namespace App\Domain\Identity;

use App\Core\Config;
use App\Core\DB;
use App\Domain\Messaging\SmsManager;
use App\Support\IranMobile;
use App\Support\Jalali;
use App\Support\Str;

final class OtpService
{
    private const TTL_SECONDS = 120;

    private const MAX_ATTEMPTS = 5;

    private const RESEND_COOLDOWN_SECONDS = 45;

    /** @return array{ok:bool,error:?string,retry_after:?int} */
    public function request(IranMobile $phone, string $purpose = 'login'): array
    {
        $ip = self::clientIp();

        $recent = DB::selectOne(
            'SELECT created_at FROM otp_codes WHERE phone = ? AND purpose = ? ORDER BY id DESC LIMIT 1',
            [$phone->e164, $purpose]
        );

        if ($recent !== null) {
            $elapsed = time() - strtotime($recent['created_at']);
            if ($elapsed < self::RESEND_COOLDOWN_SECONDS) {
                $wait = self::RESEND_COOLDOWN_SECONDS - $elapsed;

                return [
                    'ok' => false,
                    'error' => sprintf('برای ارسال دوباره، %s ثانیه دیگر صبر کنید.', Jalali::toPersianDigits((string) $wait)),
                    'retry_after' => $wait,
                ];
            }
        }

        $limitError = $this->checkHourlyLimits($phone, $ip);
        if ($limitError !== null) {
            return ['ok' => false, 'error' => $limitError, 'retry_after' => null];
        }

        $this->cleanupExpired();

        $code = Str::otp(5);

        // Any earlier unused code for this number stops working the moment a
        // new one is issued — otherwise two live codes double an attacker's odds.
        DB::update(
            'otp_codes',
            ['consumed_at' => date('Y-m-d H:i:s')],
            'phone = :phone AND purpose = :purpose AND consumed_at IS NULL',
            ['phone' => $phone->e164, 'purpose' => $purpose]
        );

        $otpId = DB::insert('otp_codes', [
            'phone' => $phone->e164,
            'code_hash' => password_hash($code, PASSWORD_DEFAULT),
            'purpose' => $purpose,
            'ip_address' => $ip,
            'attempts' => 0,
            'expires_at' => date('Y-m-d H:i:s', time() + self::TTL_SECONDS),
        ]);

        // Prefers the approved template; the plain-text form is only used when
        // no template is configured, which in production means the salon is on
        // a dedicated line (the only case where free-text actually delivers).
        $result = SmsManager::sendPattern(
            $phone->e164,
            'otp',
            [$code],
            "کد ورود شما به رشن: {$code}\nتا ۲ دقیقه معتبر است."
        );

        if (!$result['ok']) {
            // Burn the code so the user can retry immediately instead of
            // waiting out a cooldown for a message that never left.
            DB::update('otp_codes', ['consumed_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $otpId]);

            return ['ok' => false, 'error' => 'ارسال پیامک ناموفق بود: ' . ($result['error'] ?? 'خطای نامشخص'), 'retry_after' => null];
        }

        return ['ok' => true, 'error' => null, 'retry_after' => null];
    }

    /** @return array{ok:bool,error:?string} */
    public function verify(IranMobile $phone, string $code, string $purpose = 'login'): array
    {
        $row = DB::selectOne(
            'SELECT * FROM otp_codes WHERE phone = ? AND purpose = ? AND consumed_at IS NULL ORDER BY id DESC LIMIT 1',
            [$phone->e164, $purpose]
        );

        if ($row === null) {
            return ['ok' => false, 'error' => 'کدی برای این شماره درخواست نشده است.'];
        }

        if (strtotime($row['expires_at']) < time()) {
            return ['ok' => false, 'error' => 'کد منقضی شده است. دوباره درخواست کنید.'];
        }

        if ((int) $row['attempts'] >= self::MAX_ATTEMPTS) {
            return ['ok' => false, 'error' => 'تعداد تلاش‌ها بیش از حد مجاز است. کد جدید بگیرید.'];
        }

        if (!password_verify(Jalali::fromPersianDigits($code), $row['code_hash'])) {
            $attempts = (int) $row['attempts'] + 1;
            DB::update('otp_codes', ['attempts' => $attempts], 'id = :id', ['id' => $row['id']]);
            $left = self::MAX_ATTEMPTS - $attempts;

            return [
                'ok' => false,
                'error' => $left > 0
                    ? sprintf('کد وارد شده اشتباه است. %s تلاش دیگر باقی مانده.', Jalali::toPersianDigits((string) $left))
                    : 'کد وارد شده اشتباه است. کد جدید درخواست کنید.',
            ];
        }

        DB::update('otp_codes', ['consumed_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $row['id']]);

        return ['ok' => true, 'error' => null];
    }

    private function checkHourlyLimits(IranMobile $phone, ?string $ip): ?string
    {
        $since = date('Y-m-d H:i:s', time() - 3600);

        $byPhone = (int) (DB::selectOne(
            'SELECT COUNT(*) AS c FROM otp_codes WHERE phone = ? AND created_at > ?',
            [$phone->e164, $since]
        )['c'] ?? 0);

        if ($byPhone >= (int) Config::get('reshen.sms.otp_hourly_limit_phone', 5)) {
            return 'درخواست کد برای این شماره در یک ساعت گذشته زیاد بوده است. کمی بعد تلاش کنید.';
        }

        if ($ip !== null) {
            $byIp = (int) (DB::selectOne(
                'SELECT COUNT(*) AS c FROM otp_codes WHERE ip_address = ? AND created_at > ?',
                [$ip, $since]
            )['c'] ?? 0);

            if ($byIp >= (int) Config::get('reshen.sms.otp_hourly_limit_ip', 15)) {
                return 'تعداد درخواست‌ها زیاد بوده است. کمی بعد تلاش کنید.';
            }
        }

        return null;
    }

    private function cleanupExpired(): void
    {
        DB::delete('otp_codes', 'created_at < ?', [date('Y-m-d H:i:s', time() - 86400)]);
    }

    public static function clientIp(): ?string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;

        return is_string($ip) && $ip !== '' ? substr($ip, 0, 45) : null;
    }

    /**
     * The code echoed on screen in development, so the whole login flow can be
     * exercised without a real carrier.
     *
     * Never returned for a platform-admin number: if debug mode is ever left
     * on in production, anyone who knows the admin's phone could read their
     * code off the page and walk into full support access. Testing with an
     * ordinary number still works.
     */
    public static function devHint(string $e164Phone): ?string
    {
        if (!Config::get('app.debug') || Config::get('reshen.sms.driver', 'log') !== 'log') {
            return null;
        }

        $user = DB::selectOne('SELECT is_platform_admin FROM users WHERE phone = ?', [$e164Phone]);
        if ($user !== null && (int) $user['is_platform_admin'] === 1) {
            return null;
        }

        $path = BASE_PATH . '/storage/logs/sms.log';
        if (!is_file($path)) {
            return null;
        }

        $lines = file($path) ?: [];
        for ($i = count($lines) - 1; $i >= 0; $i--) {
            if (str_contains($lines[$i], $e164Phone)) {
                return trim($lines[$i]);
            }
        }

        return null;
    }
}
