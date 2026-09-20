<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Payment\DisabledGateway;
use App\Domain\Payment\PaymentGatewayManager;
use App\Domain\Payment\ZarinPalGateway;
use App\Support\Money;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * درگاه پرداخت.
 *
 * هیچ‌کدام از این تست‌ها به شبکه نمی‌زند: چیزی که سنجیده می‌شود، منطقِ
 * تصمیم است — واحد مبلغ، تفسیر کدها، و اینکه درگاه خاموش هرگز چیزی را
 * «پرداخت‌شده» علامت نزند.
 */
final class PaymentGatewayTest extends TestCase
{
    protected function tearDown(): void
    {
        PaymentGatewayManager::reset();
    }

    public function test_default_is_disabled(): void
    {
        $gateway = PaymentGatewayManager::gateway();

        self::assertSame('disabled', $gateway->name());
        self::assertFalse($gateway->isEnabled());
        self::assertFalse(PaymentGatewayManager::isEnabled());
    }

    /** مهم‌ترین تضمین: درگاه خاموش نباید چیزی را پرداخت‌شده بداند. */
    public function test_disabled_gateway_never_reports_paid(): void
    {
        $gateway = new DisabledGateway();

        $request = $gateway->request(Money::fromToman(50_000), 'https://example.test/cb');
        self::assertFalse($request['ok']);
        self::assertNull($request['redirectUrl']);
        self::assertNotNull($request['error']);

        $verify = $gateway->verify('A0000000000000000000000000000000', Money::fromToman(50_000));
        self::assertFalse($verify['paid']);
        self::assertNull($verify['refId']);
    }

    public function test_zarinpal_without_merchant_id_is_not_enabled(): void
    {
        $gateway = new ZarinPalGateway('');

        self::assertFalse($gateway->isEnabled());
        self::assertFalse($gateway->request(Money::fromToman(1000), 'https://example.test/cb')['ok']);
        self::assertFalse($gateway->verify('A000', Money::fromToman(1000))['paid']);
    }

    public function test_zarinpal_with_merchant_id_is_enabled(): void
    {
        self::assertTrue((new ZarinPalGateway('aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee'))->isEnabled());
    }

    /**
     * تومان در برابر ریال.
     *
     * زرین‌پال از نسخهٔ ۴ ریال می‌گیرد. اگر جایی تومان برود، پرداخت با
     * یک‌دهم مبلغ انجام می‌شود و تا آخر ماه کسی متوجه نمی‌شود.
     */
    public function test_money_converts_toman_to_rials(): void
    {
        self::assertSame(500_000, Money::fromToman(50_000)->rials);
        self::assertSame(50_000, Money::fromRials(50_000)->rials);
    }

    /**
     * کد ۱۰۱ یعنی «قبلاً تأیید شده»، نه خطا.
     *
     * اگر مثل خطا با آن رفتار شود، مشتری‌ای که دکمهٔ رفرش را زده پولش
     * رفته ولی نوبتش ثبت نشده.
     */
    public function test_error_text_reads_both_shapes_of_zarinpal_errors(): void
    {
        $gateway = new ZarinPalGateway('x');
        $method = new ReflectionMethod($gateway, 'errorText');

        // گاهی شیء
        self::assertSame('مبلغ نامعتبر', $method->invoke($gateway, [
            'errors' => ['code' => -11, 'message' => 'مبلغ نامعتبر'],
        ]));

        // گاهی آرایه
        self::assertSame('پذیرنده یافت نشد', $method->invoke($gateway, [
            'errors' => [['code' => -9, 'message' => 'پذیرنده یافت نشد']],
        ]));

        // و گاهی هیچ‌کدام — نباید خالی برگردد
        $fallback = $method->invoke($gateway, ['data' => ['code' => -50], 'errors' => []]);
        self::assertNotSame('', $fallback);
        self::assertStringContainsString('50', $fallback);
    }

    public function test_sandbox_and_live_use_different_hosts(): void
    {
        $base = new ReflectionMethod(ZarinPalGateway::class, 'base');

        self::assertStringContainsString('sandbox.zarinpal.com', $base->invoke(new ZarinPalGateway('x', true)));
        self::assertStringContainsString('payment.zarinpal.com', $base->invoke(new ZarinPalGateway('x', false)));
    }
}
