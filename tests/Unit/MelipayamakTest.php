<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Messaging\MelipayamakGateway;
use App\Domain\Messaging\SmsTemplates;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * پاسخ‌های ملی‌پیامک.
 *
 * چرا این تست مهم‌تر از یک تست معمولی است: ملی‌پیامک کدهای خطا را
 * داخل همان فیلدی برمی‌گرداند که شناسهٔ ارسال موفق می‌آید. اگر «2»
 * (اعتبار ناکافی) را موفق بشماریم، پیامک در `sms_messages` وضعیت
 * `sent` می‌گیرد — و چون SmsNotifier برای جلوگیری از ارسال تکراری
 * همان وضعیت را می‌خواند، آن پیامک **هرگز** دوباره فرستاده نمی‌شود.
 *
 * یعنی یک تشخیصِ غلط، پیامکِ آن مشتری را برای همیشه می‌کشد، بی‌آنکه
 * جایی خطایی ثبت شود.
 */
final class MelipayamakTest extends TestCase
{
    /** پاسخ سرویس را بدون شبکه به handle می‌دهد. */
    private function handle(array $response): array
    {
        $m = new ReflectionMethod(MelipayamakGateway::class, 'handle');
        $m->setAccessible(true);

        return $m->invoke(new MelipayamakGateway('u', 'p', '3000'), $response);
    }

    public function test_a_real_receipt_id_is_a_success(): void
    {
        $r = $this->handle(['Value' => '9876543210987654', 'RetStatus' => 1, 'StrRetStatus' => 'Ok']);

        self::assertTrue($r['ok']);
        self::assertSame('9876543210987654', $r['ref']);
        self::assertNull($r['error']);
    }

    /** @return array<string,array{string,string}> */
    public static function errorCodesInValue(): array
    {
        return [
            'اعتبار ناکافی' => ['2', 'اعتبار'],
            'لیست سیاه' => ['35', 'لیست سیاه'],
            'الگو تأیید نشده' => ['-4', 'تأیید نشده'],
            'متغیرها نمی‌خواند' => ['-5', 'متغیر'],
            'رمز اشتباه' => ['0', 'رمز'],
            'شمارهٔ نامعتبر' => ['18', 'نامعتبر'],
            'محدودیت ساعتی' => ['19', 'محدودیت ساعتی'],
            'نیاز به ApiKey' => ['-110', 'ApiKey'],
        ];
    }

    /**
     * کد خطا در `Value`، همراه با RetStatus موفق.
     *
     * دقیقاً شکلی که باگ را ساخته بود.
     */
    #[DataProvider('errorCodesInValue')]
    public function test_an_error_code_is_never_a_success(string $value, string $expect): void
    {
        $r = $this->handle(['Value' => $value, 'RetStatus' => 1, 'StrRetStatus' => 'Ok']);

        self::assertFalse($r['ok'], "«{$value}» نباید موفق شمرده شود.");
        self::assertNull($r['ref']);
        self::assertStringContainsString($expect, (string) $r['error']);
    }

    public function test_a_number_of_exactly_fifteen_digits_is_not_a_receipt(): void
    {
        // مستندات: «عددی **بیش از** ۱۵ رقم»
        self::assertFalse(MelipayamakGateway::isReceiptId('123456789012345'));
        self::assertTrue(MelipayamakGateway::isReceiptId('1234567890123456'));
    }

    public function test_a_non_numeric_value_is_not_a_receipt(): void
    {
        foreach (['', 'abc', '12345678901234567a', '-1234567890123456'] as $v) {
            self::assertFalse(MelipayamakGateway::isReceiptId($v), "«{$v}»");
        }
    }

    public function test_an_unknown_code_still_produces_a_readable_message(): void
    {
        $r = $this->handle(['Value' => '99', 'RetStatus' => 99]);

        self::assertFalse($r['ok']);
        self::assertStringContainsString('99', (string) $r['error']);
    }

    public function test_a_transport_failure_is_reported_as_given(): void
    {
        $r = $this->handle(['_error' => 'ارتباط برقرار نشد.']);

        self::assertFalse($r['ok']);
        self::assertSame('ارتباط برقرار نشد.', $r['error']);
    }

    // ─── ثبت الگو ────────────────────────────────────────────────────

    public function test_the_soap_envelope_is_valid_xml_and_escapes_input(): void
    {
        $xml = MelipayamakGateway::buildPatternEnvelope(
            'user',
            'p&ss<word>',
            'کد ورود',
            "کد ورود شما: {0}\nبه کسی ندهید."
        );

        self::assertNotFalse(simplexml_load_string($xml), 'پاکت باید XML معتبر باشد.');
        self::assertStringContainsString('p&amp;ss&lt;word&gt;', $xml, 'رمز باید escape شود.');
        self::assertStringContainsString('<blackListId>1</blackListId>', $xml);
        self::assertStringContainsString('SharedServiceBodyAdd', $xml);
    }

    public function test_the_body_id_is_read_out_of_the_soap_response(): void
    {
        $xml = '<?xml version="1.0"?><soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">'
            . '<soap:Body><SharedServiceBodyAddResponse xmlns="http://tempuri.org/">'
            . '<SharedServiceBodyAddResult>123456</SharedServiceBodyAddResult>'
            . '</SharedServiceBodyAddResponse></soap:Body></soap:Envelope>';

        self::assertSame('123456', MelipayamakGateway::extractPatternResult($xml));
    }

    public function test_a_soap_fault_is_surfaced_not_swallowed(): void
    {
        $xml = '<?xml version="1.0"?><soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">'
            . '<soap:Body><soap:Fault><faultstring>Server error</faultstring></soap:Fault>'
            . '</soap:Body></soap:Envelope>';

        self::assertNull(MelipayamakGateway::extractPatternResult($xml));
        self::assertStringContainsString('Server error', (string) MelipayamakGateway::extractSoapFault($xml));
    }

    /** @return array<string,array{string,bool}> */
    public static function patternResults(): array
    {
        return [
            'شناسهٔ شش‌رقمی' => ['123456', true],
            'شناسهٔ پنج‌رقمی' => ['98765', true],
            'شناسهٔ لیست سیاه غلط' => ['-2', false],
            'رمز غلط' => ['0', false],
            'پاسخ خالی' => ['', false],
            'پاسخ نامفهوم' => ['error', false],
        ];
    }

    #[DataProvider('patternResults')]
    public function test_pattern_registration_results_are_interpreted(string $raw, bool $ok): void
    {
        $r = MelipayamakGateway::interpretPatternResult($raw);

        self::assertSame($ok, $r['ok']);
        if ($ok) {
            self::assertSame($raw, $r['body_id']);
        } else {
            self::assertNull($r['body_id']);
            self::assertNotNull($r['error']);
        }
    }

    // ─── شکل متغیرها ─────────────────────────────────────────────────

    public function test_the_registration_text_uses_the_providers_own_placeholders(): void
    {
        // «‎%name%‎» شکل داخلی رشن است و برای هیچ اپراتوری معنا ندارد.
        // ثبت کردنش یعنی الگویی بدون متغیر، و ارسال با کد ‎-5‎.
        $meli = SmsTemplates::providerPattern('booking_confirmed', 'melipayamak');
        self::assertStringNotContainsString('%name%', $meli);
        self::assertStringContainsString('{0}', $meli);
        self::assertStringContainsString('{3}', $meli);

        $kave = SmsTemplates::providerPattern('booking_confirmed', 'kavenegar');
        self::assertStringNotContainsString('%name%', $kave);
        self::assertStringContainsString('%token%', $kave);
        self::assertStringContainsString('%token4%', $kave);
    }

    public function test_placeholder_order_matches_the_variable_order(): void
    {
        // ارسال، مقادیر را به ترتیب `vars` می‌فرستد. اگر شماره‌گذاری
        // الگو با آن ترتیب یکی نباشد، جای اسم، ساعت می‌نشیند.
        foreach (SmsTemplates::all() as $code => $template) {
            $text = SmsTemplates::providerPattern($code, 'melipayamak');
            foreach (array_keys($template['vars']) as $i) {
                self::assertStringContainsString(
                    '{' . $i . '}',
                    $text,
                    "الگوی «{$code}» متغیر شمارهٔ {$i} را ندارد."
                );
            }
            self::assertStringNotContainsString('%', $text, "الگوی «{$code}» هنوز متغیر رشن دارد.");
        }
    }
}
