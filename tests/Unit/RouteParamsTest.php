<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * پارامترهای مسیر.
 *
 * چرا این تست هست: نشانی‌ای که مرورگر می‌فرستد درصدرمزگذاری‌شده است.
 * وقتی روتر مقدار خام را به کنترلر می‌داد، یک slug فارسی به‌شکل
 * %D8%A2%D8%B1... می‌رسید و هرگز با چیزی که در دیتابیس نشسته بود جور
 * نمی‌شد. نتیجه: صفحهٔ عمومی سالن ۴۰۴ می‌داد — و چون ثبت‌نام از روی
 * نام فارسی slug فارسی می‌ساخت، این یعنی *هر* سالنی که از مسیر عادی
 * ساخته می‌شد لینک عمومی‌اش از کار می‌افتاد. هیچ تستی این را نمی‌دید
 * چون تست‌ها مستقیم با مقدار فارسی صدا زده می‌شدند، نه با نشانی واقعی.
 */
final class RouteParamsTest extends TestCase
{
    private function dispatch(string $requestUri): ?string
    {
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['REQUEST_URI'] = $requestUri;
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $seen = null;
        $router = new Router();
        $router->get('/s/{slug}', function (Request $req) use (&$seen): Response {
            $seen = $req->routeParams['slug'] ?? null;

            return new Response('');
        });

        $router->dispatch(new Request());

        return $seen;
    }

    /** @return array<string,array{string,string}> */
    public static function slugs(): array
    {
        return [
            'انگلیسی ساده' => ['/s/shahab', 'shahab'],
            'فارسی' => ['/s/' . rawurlencode('آرایشگاه-شهاب'), 'آرایشگاه-شهاب'],
            'فارسی با رقم' => ['/s/' . rawurlencode('سالن-۲۴'), 'سالن-۲۴'],
            'فاصله' => ['/s/' . rawurlencode('two words'), 'two words'],
            'خط تیره و زیرخط' => ['/s/a-b_c', 'a-b_c'],
        ];
    }

    #[DataProvider('slugs')]
    public function test_route_parameter_reaches_the_controller_decoded(
        string $uri,
        string $expected
    ): void {
        self::assertSame($expected, $this->dispatch($uri));
    }

    public function test_encoded_slash_cannot_escape_its_segment(): void
    {
        // %2F پس از رمزگشایی «/» می‌شود. اگر پیش از تطبیق رمزگشایی
        // می‌کردیم، این می‌توانست الگوی مسیر را بشکند و درخواست را جای
        // دیگری ببرد. تطبیق روی نشانی خام انجام می‌شود، پس اینجا فقط
        // یک مقدارِ بی‌آزار است که «/» درونش دارد.
        self::assertSame('a/b', $this->dispatch('/s/a%2Fb'));
    }

    public function test_unmatched_path_yields_no_parameter(): void
    {
        self::assertNull($this->dispatch('/s/a/b'));
    }
}
