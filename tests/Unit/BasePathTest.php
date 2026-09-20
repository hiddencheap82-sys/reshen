<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * کشف پیشوند آدرس.
 *
 * چرا این تست مهم است: اگر basePath اشتباه باشد، روی هاستی که پروژه در
 * زیرپوشه نصب شده همهٔ لینک‌ها ۴۰۴ می‌شوند — و این چیزی است که فقط روی
 * هاست واقعی مشتری دیده می‌شود، نه روی لپ‌تاپ توسعه‌دهنده.
 */
final class BasePathTest extends TestCase
{
    /** @return array<string,array{string,string,string}> */
    public static function layouts(): array
    {
        return [
            // نام حالت => [SCRIPT_NAME, REQUEST_URI, پیشوند انتظاری]
            'ریشهٔ دامنه، docroot روی public'      => ['/index.php', '/panel', ''],
            'زیرپوشه، docroot روی public'          => ['/reshen/index.php', '/reshen/panel', '/reshen'],
            'ریشه، کل پروژه در public_html'        => ['/public/index.php', '/panel', ''],
            'زیرپوشه، کل پروژه در public_html'     => ['/reshen/public/index.php', '/reshen/panel', '/reshen'],
            'زیرپوشهٔ تودرتو'                      => ['/a/b/index.php', '/a/b/panel', '/a/b'],
            'کاربر عمداً /public را باز کرده'       => ['/public/index.php', '/public/panel', '/public'],
        ];
    }

    #[DataProvider('layouts')]
    public function test_base_path_is_detected(string $scriptName, string $requestUri, string $expected): void
    {
        $_SERVER['SCRIPT_NAME'] = $scriptName;
        $_SERVER['REQUEST_URI'] = $requestUri;

        self::assertSame($expected, Request::basePath());
    }

    #[DataProvider('layouts')]
    public function test_path_has_base_prefix_stripped(string $scriptName, string $requestUri, string $expected): void
    {
        $_SERVER['SCRIPT_NAME'] = $scriptName;
        $_SERVER['REQUEST_URI'] = $requestUri;
        $_SERVER['REQUEST_METHOD'] = 'GET';

        // مسیری که روتر می‌بیند نباید هرگز پیشوند را داشته باشد.
        self::assertSame('/panel', (new Request())->path);
    }

    public function test_query_string_is_not_part_of_path(): void
    {
        $_SERVER['SCRIPT_NAME'] = '/reshen/public/index.php';
        $_SERVER['REQUEST_URI'] = '/reshen/panel?from=sms&x=1';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        self::assertSame('/panel', (new Request())->path);
    }
}
