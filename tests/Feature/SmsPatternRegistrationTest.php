<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\DB;
use App\Domain\Messaging\SmsPatternRepository;
use App\Domain\Messaging\SmsTemplates;
use PHPUnit\Framework\TestCase;

/**
 * حافظهٔ الگوهای ثبت‌شده.
 *
 * شناسه‌ای که اپراتور برمی‌گرداند تا چند روز کار نمی‌کند (تا تأیید
 * شود). در آن فاصله تنها چیزی که صاحب سالن دارد همین جدول است — اگر
 * یادش نماند چه ثبت کرده، دوباره ثبت می‌کند و یک شناسهٔ یتیم دیگر
 * می‌سازد.
 */
final class SmsPatternRegistrationTest extends TestCase
{
    private SmsPatternRepository $repo;

    protected function setUp(): void
    {
        DB::statement('DELETE FROM sms_pattern_registrations WHERE 1');
        $this->repo = new SmsPatternRepository();
    }

    public function test_a_registration_is_remembered(): void
    {
        $body = SmsTemplates::providerPattern('otp', 'melipayamak');
        $this->repo->remember('melipayamak', 'otp', '123456', 'کد ورود', $body, null);

        $all = $this->repo->forProvider('melipayamak');

        self::assertArrayHasKey('otp', $all);
        self::assertSame('123456', $all['otp']['body_id']);
        self::assertSame($body, $all['otp']['body'], 'متنِ دقیقاً ثبت‌شده باید بماند.');
    }

    public function test_registering_again_replaces_the_old_id(): void
    {
        // متن الگو عوض شده و دوباره ثبت شده. نگه داشتن هر دو شناسه
        // یعنی صاحب سالن نمی‌داند کدام در .env است.
        $this->repo->remember('melipayamak', 'otp', '111111', 'کد ورود', 'قدیمی {0}', null);
        $this->repo->remember('melipayamak', 'otp', '222222', 'کد ورود', 'تازه {0}', null);

        $all = $this->repo->forProvider('melipayamak');

        self::assertCount(1, $all);
        self::assertSame('222222', $all['otp']['body_id']);
        self::assertSame('تازه {0}', $all['otp']['body']);
    }

    public function test_providers_keep_separate_ids_for_the_same_template(): void
    {
        // یک الگو نزد دو اپراتور دو شناسهٔ متفاوت دارد. قاطی شدنشان
        // یعنی پیامکِ پشتیبان با شناسهٔ اپراتور اصلی فرستاده می‌شود و
        // بی‌صدا رد می‌شود.
        $this->repo->remember('melipayamak', 'otp', '123456', 'کد ورود', '{0}', null);
        $this->repo->remember('kavenegar', 'otp', 'reshen-otp', 'کد ورود', '%token%', null);

        self::assertSame('123456', $this->repo->forProvider('melipayamak')['otp']['body_id']);
        self::assertSame('reshen-otp', $this->repo->forProvider('kavenegar')['otp']['body_id']);
    }

    public function test_forgetting_removes_only_that_one(): void
    {
        $this->repo->remember('melipayamak', 'otp', '1', 'الف', '{0}', null);
        $this->repo->remember('melipayamak', 'reminder_24h', '2', 'ب', '{0}', null);

        $this->repo->forget('melipayamak', 'otp');
        $all = $this->repo->forProvider('melipayamak');

        self::assertArrayNotHasKey('otp', $all);
        self::assertArrayHasKey('reminder_24h', $all);
    }

    public function test_an_unknown_provider_has_nothing(): void
    {
        self::assertSame([], $this->repo->forProvider('nobody'));
    }
}
