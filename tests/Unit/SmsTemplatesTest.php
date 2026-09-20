<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Config;
use App\Domain\Messaging\SmsTemplates;
use PHPUnit\Framework\TestCase;

/**
 * الگوهای پیامک.
 *
 * چرا تست دارد: اگر ترتیب متغیرها با آنچه در پنل اپراتور ثبت شده یکی
 * نباشد، پیامک می‌رود ولی جای اسمِ مشتری، ساعت می‌نشیند — و هیچ خطایی
 * هم بالا نمی‌آید. این را فقط مشتری‌ای که زنگ بزند لو می‌دهد.
 */
final class SmsTemplatesTest extends TestCase
{
    public function test_ordered_args_follow_declared_order_not_input_order(): void
    {
        // عمداً به‌هم‌ریخته داده می‌شود
        $args = SmsTemplates::orderedArgs('queue_nearly_up', [
            'minutes' => '۲۰',
            'name' => 'علی',
            'ahead' => '۲',
        ]);

        self::assertSame(['علی', '۲', '۲۰'], $args);
    }

    public function test_missing_variable_becomes_empty_string_not_error(): void
    {
        $args = SmsTemplates::orderedArgs('queue_chair_ready', ['name' => 'رضا']);

        self::assertSame(['رضا', ''], $args, 'متغیر جامانده نباید آرایه را کوتاه کند');
    }

    public function test_render_substitutes_all_placeholders(): void
    {
        $text = SmsTemplates::render('booking_confirmed', [
            'name' => 'مهدی',
            'salon' => 'آرایشگاه شهاب',
            'date' => 'شنبه ۴ مهر',
            'time' => '۱۷:۳۰',
        ]);

        self::assertStringContainsString('مهدی', $text);
        self::assertStringContainsString('آرایشگاه شهاب', $text);
        self::assertStringContainsString('۱۷:۳۰', $text);
        self::assertStringNotContainsString('%', $text, 'هیچ جای‌گیرِ پر نشده نباید بماند');
    }

    /** هر متغیرِ اعلام‌شده باید در متن الگو جای‌گیر داشته باشد، و برعکس. */
    public function test_every_declared_variable_appears_in_pattern(): void
    {
        foreach (SmsTemplates::all() as $code => $t) {
            foreach ($t['vars'] as $var) {
                self::assertStringContainsString(
                    '%' . $var . '%',
                    $t['pattern'],
                    "الگوی «{$code}» متغیر «{$var}» را اعلام کرده ولی در متنش نیست"
                );
            }

            preg_match_all('/%([a-z_0-9]+)%/', $t['pattern'], $m);
            foreach (array_unique($m[1]) as $found) {
                self::assertContains(
                    $found,
                    $t['vars'],
                    "الگوی «{$code}» جای‌گیر «{$found}» دارد که در فهرست متغیرها نیست"
                );
            }
        }
    }

    /** هر الگو باید در پیکربندی برای هر دو اپراتور کلید داشته باشد. */
    public function test_every_template_has_a_config_key_for_both_providers(): void
    {
        foreach (array_keys(SmsTemplates::all()) as $code) {
            foreach (['melipayamak', 'kavenegar'] as $provider) {
                self::assertNotNull(
                    Config::get("reshen.sms.patterns.{$provider}.{$code}"),
                    "کلید «{$provider}.{$code}» در config/reshen.php نیست"
                );
            }
        }
    }

    public function test_unknown_code_is_handled_quietly(): void
    {
        self::assertFalse(SmsTemplates::exists('no_such_template'));
        self::assertNull(SmsTemplates::get('no_such_template'));
        self::assertSame([], SmsTemplates::orderedArgs('no_such_template', ['a' => 'b']));
        self::assertSame('', SmsTemplates::render('no_such_template', []));
    }

    public function test_critical_messages_are_the_ones_that_must_arrive(): void
    {
        // ورود و «نوبتت رسید» حتی در ساعات سکوت هم باید بروند
        self::assertTrue(SmsTemplates::isCritical('otp'));
        self::assertTrue(SmsTemplates::isCritical('queue_chair_ready'));
        // یادآوری‌ها نه
        self::assertFalse(SmsTemplates::isCritical('reminder_24h'));
        self::assertFalse(SmsTemplates::isCritical('queue_nearly_up'));
    }

    /** متن الگو نباید لینک یا رقم لاتین داشته باشد — اپراتور رد می‌کند. */
    public function test_patterns_have_no_urls(): void
    {
        foreach (SmsTemplates::all() as $code => $t) {
            self::assertDoesNotMatchRegularExpression(
                '#https?://#',
                $t['pattern'],
                "الگوی «{$code}» لینک دارد؛ لینکِ متغیر در الگوی ثبت‌شده مجاز نیست"
            );
        }
    }
}
