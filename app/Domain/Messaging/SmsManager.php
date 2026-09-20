<?php

declare(strict_types=1);

namespace App\Domain\Messaging;

use App\Core\Config;
use App\Core\Env;

final class SmsManager
{
    private static ?SmsGatewayInterface $primary = null;

    private static ?SmsGatewayInterface $fallback = null;

    /**
     * Sends via the configured primary driver; if that fails and a real
     * fallback provider is configured, retries once through it. Returns
     * which provider actually delivered it (or failure details).
     *
     * @return array{ok:bool,provider:string,ref:?string,error:?string}
     */
    public static function send(string $e164Phone, string $message): array
    {
        return self::attempt(static fn (SmsGatewayInterface $gateway) => $gateway->send($e164Phone, $message));
    }

    /**
     * Sends through a pre-approved template, falling back to plain text when
     * no template is configured for the provider that is actually handling
     * the send.
     *
     * `$patternKey` is resolved per provider: the same logical message has a
     * different id at Melipayamak (a numeric bodyId) than at Kavenegar (a
     * template name), so the backup provider must never reuse the primary's.
     *
     * @param string[] $args
     */
    public static function sendPattern(string $e164Phone, string $patternKey, array $args, string $fallbackText = ''): array
    {
        return self::attempt(static function (SmsGatewayInterface $gateway) use ($e164Phone, $patternKey, $args, $fallbackText) {
            $patternId = (string) Config::get('reshen.sms.patterns.' . $gateway->name() . '.' . $patternKey, '');

            if ($patternId === '') {
                if ($fallbackText === '') {
                    return ['ok' => false, 'ref' => null, 'error' => "الگوی «{$patternKey}» برای {$gateway->name()} تنظیم نشده است."];
                }

                return $gateway->send($e164Phone, $fallbackText);
            }

            return $gateway->sendPattern($e164Phone, $patternId, $args);
        });
    }

    /** @param callable(SmsGatewayInterface):array $send */
    private static function attempt(callable $send): array
    {
        $primary = self::primary();
        $result = $send($primary);

        if ($result['ok']) {
            return ['ok' => true, 'provider' => $primary->name(), 'ref' => $result['ref'], 'error' => null];
        }

        $fallback = self::fallback();
        if ($fallback !== null) {
            $fallbackResult = $send($fallback);
            if ($fallbackResult['ok']) {
                return ['ok' => true, 'provider' => $fallback->name(), 'ref' => $fallbackResult['ref'], 'error' => null];
            }

            return ['ok' => false, 'provider' => $fallback->name(), 'ref' => null, 'error' => $fallbackResult['error']];
        }

        return ['ok' => false, 'provider' => $primary->name(), 'ref' => null, 'error' => $result['error']];
    }

    private static function primary(): SmsGatewayInterface
    {
        if (self::$primary === null) {
            self::$primary = self::make((string) Config::get('reshen.sms.driver', 'log'));
        }

        return self::$primary;
    }

    private static function fallback(): ?SmsGatewayInterface
    {
        $driver = Config::get('reshen.sms.driver', 'log');
        if ($driver !== 'melipayamak') {
            return null; // only the real primary needs a real backup
        }
        if (self::$fallback === null) {
            self::$fallback = self::make('kavenegar');
        }

        return self::$fallback;
    }

    private static function make(string $driver): SmsGatewayInterface
    {
        return match ($driver) {
            'melipayamak' => new MelipayamakGateway(
                (string) Env::get('SMS_MELIPAYAMAK_USERNAME', ''),
                (string) Env::get('SMS_MELIPAYAMAK_PASSWORD', ''),
                (string) Env::get('SMS_MELIPAYAMAK_SENDER', ''),
            ),
            'kavenegar' => new KavenegarGateway((string) Env::get('SMS_KAVENEGAR_API_KEY', '')),
            default => new LogSmsGateway(),
        };
    }
}
