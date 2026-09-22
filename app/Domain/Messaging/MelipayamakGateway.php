<?php

declare(strict_types=1);

namespace App\Domain\Messaging;

/**
 * Primary Iranian SMS provider, over Melipayamak's REST API (so the host
 * only needs curl — no php-soap dependency, which doc 8.7 warns is the
 * silent killer of first-day SMS delivery).
 *
 * Two send modes, and the difference matters operationally:
 *   - BaseServiceNumber: a pre-approved template ("الگو") identified by a
 *     bodyId. Required for OTP and any transactional message, because
 *     carriers do not deliver free-text transactional SMS over the shared
 *     service lines most salons will be on.
 *   - SendSMS: free text, but only works from a dedicated purchased line
 *     (خط اختصاصی).
 */
final class MelipayamakGateway implements SmsGatewayInterface
{
    private const ENDPOINT_PATTERN = 'https://rest.payamak-panel.com/api/SendSMS/BaseServiceNumber';
    private const ENDPOINT_SIMPLE = 'https://rest.payamak-panel.com/api/SendSMS/SendSMS';
    private const ENDPOINT_CREDIT = 'https://rest.payamak-panel.com/api/SendSMS/GetCredit';

    public function __construct(
        private readonly string $username,
        private readonly string $password,
        private readonly string $sender,
    ) {
    }

    public function send(string $e164Phone, string $message): array
    {
        if ($this->sender === '') {
            return ['ok' => false, 'ref' => null, 'error' => 'خط اختصاصی (شمارهٔ فرستنده) تنظیم نشده است.'];
        }

        return $this->handle($this->request(self::ENDPOINT_SIMPLE, [
            'username' => $this->username,
            'password' => $this->password,
            'to' => $this->localNumber($e164Phone),
            'from' => $this->sender,
            'text' => $message,
            'isflash' => 'false',
        ]));
    }

    public function sendPattern(string $e164Phone, string $patternId, array $args): array
    {
        if ($patternId === '') {
            return ['ok' => false, 'ref' => null, 'error' => 'شناسهٔ الگو (bodyId) تنظیم نشده است.'];
        }

        // متغیرهای الگو با «;» جدا می‌شوند، پس هیچ مقداری نباید این
        // نویسه را داشته باشد — وگرنه پیامک با متن جابه‌جا ارسال می‌شود.
        $text = implode(';', array_map([$this, 'cleanParam'], $args));

        return $this->handle($this->request(self::ENDPOINT_PATTERN, [
            'username' => $this->username,
            'password' => $this->password,
            'text' => $text,
            'to' => $this->localNumber($e164Phone),
            'bodyId' => $patternId,
        ]));
    }

    /**
     * اعتبار پنل — برای صفحهٔ نصب و سلامت.
     *
     * بدون این، اعتبار تمام‌شده بی‌صدا شکست می‌خورد: پیامک‌ها ارسال
     * «موفق» می‌گیرند و هیچ‌وقت نمی‌رسند.
     */
    public function credit(): ?float
    {
        $response = $this->request(self::ENDPOINT_CREDIT, [
            'username' => $this->username,
            'password' => $this->password,
        ]);

        if (($response['RetStatus'] ?? 0) === 1) {
            return (float) ($response['Value'] ?? 0);
        }

        return null;
    }

    public function name(): string
    {
        return 'melipayamak';
    }

    private function request(string $url, array $body): array
    {
        if ($this->username === '' || $this->password === '') {
            return ['_error' => 'نام کاربری یا رمز ملی‌پیامک تنظیم نشده است.'];
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($body),
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded; charset=utf-8'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
        ]);
        $raw = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false) {
            return ['_error' => $curlError !== '' ? $curlError : 'ارتباط با سرویس پیامک برقرار نشد.'];
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            return ['_error' => sprintf('خطای ارتباط با سرویس پیامک (کد %d).', $httpCode)];
        }

        $decoded = json_decode((string) $raw, true);

        if (!is_array($decoded)) {
            return ['_error' => 'پاسخ سرویس پیامک قابل خواندن نبود.'];
        }

        return $decoded;
    }

    /**
     * در حالت موفق، `Value` شناسهٔ ارسال است.
     *
     * هر چیز دیگری شکست است — از جمله «0» با وضعیتی که موفق به نظر
     * می‌رسد، که حالت واقعیِ «اعتبار ندارید» است.
     */
    private function handle(array $response): array
    {
        if (isset($response['_error'])) {
            return ['ok' => false, 'ref' => null, 'error' => $response['_error']];
        }

        $status = (int) ($response['RetStatus'] ?? 0);
        $value = (string) ($response['Value'] ?? '');

        if ($status === 1 && $value !== '' && $value !== '0') {
            return ['ok' => true, 'ref' => $value, 'error' => null];
        }

        return ['ok' => false, 'ref' => null, 'error' => self::statusMessage($status)];
    }

    public static function statusMessage(int $status): string
    {
        $map = [
            0 => 'نام کاربری یا رمز عبور اشتباه است.',
            2 => 'اعتبار کافی نیست.',
            3 => 'محدودیت در ارسال روزانه.',
            4 => 'محدودیت در حجم ارسال.',
            5 => 'شمارهٔ فرستنده معتبر نیست.',
            6 => 'سامانه در حال به‌روزرسانی است.',
            7 => 'متن پیام حاوی کلمهٔ فیلترشده است.',
            9 => 'ارسال از خطوط عمومی از طریق وب‌سرویس امکان‌پذیر نیست.',
            10 => 'کاربر مورد نظر فعال نیست.',
            11 => 'ارسال نشد.',
            12 => 'مدارک کاربر کامل نیست.',
            14 => 'متن پیامک با الگوی تعریف‌شده مطابقت ندارد.',
            15 => 'ارسال از خطوط عمومی مجاز نیست.',
            16 => 'شمارهٔ گیرنده یافت نشد.',
            17 => 'متن پیامک خالی است.',
            35 => 'شماره در لیست سیاه مخابرات است.',
        ];

        return $map[$status] ?? sprintf('ارسال پیامک ناموفق بود (کد %d).', $status);
    }

    private function cleanParam(string $value): string
    {
        return str_replace([';', "\r", "\n"], ['،', ' ', ' '], $value);
    }

    private function localNumber(string $e164Phone): string
    {
        return '0' . substr($e164Phone, 3);
    }
}
