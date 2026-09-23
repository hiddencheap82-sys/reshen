<?php

declare(strict_types=1);

use App\Http\Controllers\AuthController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\ManifestController;
use App\Http\Middleware\AuthRequired;
use App\Http\Middleware\VerifyCsrf;

/** @var \App\Core\Router $router */

/*
 * صفحهٔ نخست. پیش از این مستقیم به /login می‌رفت، که فقط برای صاحب
 * سالن درست بود — مشتری به فرمی می‌رسید که هیچ ربطی به او نداشت.
 */
$router->get('/', [LandingController::class, 'index']);

// مانیفست PWA — در لحظه ساخته می‌شود تا برای هر سالن، اسم و صفحهٔ
// شروعِ خودش را بدهد (ت-۳۲).
$router->get('/manifest.webmanifest', [ManifestController::class, 'show']);

// ─── ورود و خروج ─────────────────────────────────────────────────────
$router->get('/login', [AuthController::class, 'showLogin']);
$router->get('/login/verify', [AuthController::class, 'showVerify']);
$router->get('/logout', [AuthController::class, 'logout']);

/*
 * ورود با لینک یک‌بارمصرف (ت-۳۶). فقط توکنی که از روی سرور ساخته شده
 * کار می‌کند؛ هیچ راهی برای درخواستِ لینک از وب نیست.
 */
$router->get('/login/link/{token}', [AuthController::class, 'loginWithLink']);

$router->group(['middleware' => [VerifyCsrf::class]], function ($router) {
    /*
     * ‎POST /login‎ حالا ورود با رمز است و کد پیامکی مسیر خودش را دارد.
     *
     * جای این دو عمدی است: فرمِ پیش‌فرضِ صفحه، رمز می‌خواهد، چون این
     * صفحه ورودِ *کارکنان* است و آن‌ها روزی چند بار وارد می‌شوند.
     * مشتری از ‎/me‎ وارد می‌شود که همچنان فقط کد پیامکی است.
     */
    $router->post('/login', [AuthController::class, 'loginWithPassword']);
    $router->post('/login/otp', [AuthController::class, 'sendOtp']);
    $router->post('/login/verify', [AuthController::class, 'verify']);
});

$router->group(['middleware' => [AuthRequired::class]], function ($router) {
    $router->get('/salons', [AuthController::class, 'pickSalon']);
    $router->get('/salons/{id}/switch', [AuthController::class, 'switchSalon']);
});

require __DIR__ . '/onboarding.php';
require __DIR__ . '/panel.php';
require __DIR__ . '/booking.php';
require __DIR__ . '/customer.php';
require __DIR__ . '/platform.php';
