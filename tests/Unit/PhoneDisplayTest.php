<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * شماره، به شکلی که ایرانی‌ها می‌خوانند.
 *
 * شماره‌ها بین‌المللی ذخیره می‌شوند (‎+98912…‎) — برای پیامک و «تماس»
 * درست — ولی روی صفحه غریبه‌اند. چند صفحه شکل خام را چاپ می‌کردند.
 */
final class PhoneDisplayTest extends TestCase
{
    public function test_an_international_mobile_reads_the_local_way(): void
    {
        self::assertSame('۰۹۱۲ ۱۱۱ ۲۲۳۳', phone_display('+989121112233'));
    }

    public function test_a_local_mobile_is_grouped_the_same_way(): void
    {
        self::assertSame('۰۹۱۲ ۱۱۱ ۲۲۳۳', phone_display('09121112233'));
    }

    /** شمارهٔ ثابتِ سالن را IranMobile نمی‌پذیرد؛ نباید خطا بدهد یا گم شود. */
    public function test_a_landline_is_kept_as_is_with_persian_digits(): void
    {
        self::assertSame('۰۲۱۸۸۷۷۶۶۵۵', phone_display('02188776655'));
    }

    public function test_nothing_in_nothing_out(): void
    {
        self::assertSame('', phone_display(null));
        self::assertSame('', phone_display('   '));
    }
}
