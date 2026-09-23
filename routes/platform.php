<?php

declare(strict_types=1);

use App\Http\Controllers\PlatformController;
use App\Http\Middleware\PlatformAdminRequired;
use App\Http\Middleware\VerifyCsrf;

/** @var \App\Core\Router $router */

/*
 * پنل پلتفرم — بالاترین دسترسی.
 *
 * همهٔ مسیرها پشت PlatformAdminRequired هستند، بدون استثنا. مسیرهای
 * نام‌دار پیش از /platform/{id} می‌آیند چون روتر اولین تطبیق را
 * برمی‌دارد: اگر «salons» بعد از «{id}» بیاید، به‌جای فهرست سالن‌ها
 * دنبال سالنی با شناسهٔ صفر می‌گردد.
 */
$router->group(['middleware' => [PlatformAdminRequired::class]], function ($router) {
    $router->get('/platform', [PlatformController::class, 'index']);
    $router->get('/platform/salons', [PlatformController::class, 'salons']);
    $router->get('/platform/salons/create', [PlatformController::class, 'createSalon']);
    $router->get('/platform/health', [PlatformController::class, 'health']);
    $router->get('/platform/support', [PlatformController::class, 'support']);
    $router->get('/platform/support/{id}', [PlatformController::class, 'supportShow']);
    $router->get('/platform/plans', [PlatformController::class, 'plans']);
    $router->get('/platform/invoices', [PlatformController::class, 'invoices']);
    $router->get('/platform/users', [PlatformController::class, 'users']);
    // مرجع سیستم دیزاین — برای توسعه‌دهنده، نه آرایشگر.
    $router->get('/platform/design', [PlatformController::class, 'design']);
    $router->get('/platform/sms', [PlatformController::class, 'sms']);
    $router->get('/platform/activity', [PlatformController::class, 'activity']);
    $router->get('/platform/impersonate/stop', [PlatformController::class, 'stopImpersonating']);

    $router->get('/platform/{id}', [PlatformController::class, 'show']);

    $router->group(['middleware' => [VerifyCsrf::class]], function ($router) {
        $router->post('/platform/invoices/{id}/pay', [PlatformController::class, 'payInvoice']);
        $router->post('/platform/invoices/{id}/cancel', [PlatformController::class, 'cancelInvoice']);
        $router->post('/platform/users/{id}/admin', [PlatformController::class, 'togglePlatformAdmin']);
        // ساخت کاربر و بازنشانی رمز — جایگزین tools/make_platform_admin.php
        // که به SSH نیاز داشت و روی هاست اشتراکی قابل اجرا نبود.
        $router->post('/platform/users', [PlatformController::class, 'storeUser']);
        $router->post('/platform/users/{id}/password', [PlatformController::class, 'resetUserPassword']);

        $router->post('/platform/salons', [PlatformController::class, 'storeSalon']);
        $router->post('/platform/support/{id}/reply', [PlatformController::class, 'supportReply']);
        $router->post('/platform/support/{id}/close', [PlatformController::class, 'supportClose']);

        $router->post('/platform/{id}/impersonate', [PlatformController::class, 'impersonate']);
        $router->post('/platform/{id}/plan', [PlatformController::class, 'updatePlan']);
        $router->post('/platform/{id}/active', [PlatformController::class, 'toggleActive']);
        $router->post('/platform/{id}/invoice', [PlatformController::class, 'issueInvoice']);
    });
});
