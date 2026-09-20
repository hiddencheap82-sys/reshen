<?php

declare(strict_types=1);

use App\Core\Response;
use App\Http\Controllers\AuthController;
use App\Http\Middleware\AuthRequired;
use App\Http\Middleware\VerifyCsrf;

/** @var \App\Core\Router $router */

$router->get('/', function () {
    return Response::redirect('/login');
});

// --- Auth -----------------------------------------------------------------
$router->get('/login', [AuthController::class, 'showLogin']);
$router->get('/login/verify', [AuthController::class, 'showVerify']);
$router->get('/logout', [AuthController::class, 'logout']);

$router->group(['middleware' => [VerifyCsrf::class]], function ($router) {
    $router->post('/login', [AuthController::class, 'sendOtp']);
    $router->post('/login/verify', [AuthController::class, 'verify']);
});

$router->group(['middleware' => [AuthRequired::class]], function ($router) {
    $router->get('/salons', [AuthController::class, 'pickSalon']);
    $router->get('/salons/{id}/switch', [AuthController::class, 'switchSalon']);
});

require __DIR__ . '/onboarding.php';
require __DIR__ . '/panel.php';
require __DIR__ . '/booking.php';
require __DIR__ . '/platform.php';
