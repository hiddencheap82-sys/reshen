<?php

declare(strict_types=1);

use App\Core\Response;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\QueueController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\SalonSettingsController;
use App\Http\Controllers\StaffController;
use App\Http\Middleware\AuthRequired;
use App\Http\Middleware\OwnerManagerRequired;
use App\Http\Middleware\TenantRequired;
use App\Http\Middleware\VerifyCsrf;

/** @var \App\Core\Router $router */

$router->group(['middleware' => [AuthRequired::class, TenantRequired::class]], function ($router) {
    $router->get('/panel', [QueueController::class, 'index']);
    $router->get('/panel/queue/poll', [QueueController::class, 'poll']);
    $router->get('/panel/pay/{id}', [PaymentController::class, 'show']);
    $router->get('/panel/setup', function () {
        return Response::redirect('/panel/staff');
    });

    $router->get('/panel/customers', [CustomerController::class, 'index']);
    $router->get('/panel/customers/{id}', [CustomerController::class, 'show']);

    $router->group(['middleware' => [VerifyCsrf::class]], function ($router) {
        $router->post('/panel/customers/{id}', [CustomerController::class, 'update']);
        $router->post('/panel/queue/walkin', [QueueController::class, 'addWalkin']);
        $router->post('/panel/queue/{id}/start', [QueueController::class, 'start']);
        $router->post('/panel/queue/{id}/complete', [QueueController::class, 'complete']);
        $router->post('/panel/queue/{id}/no-show', [QueueController::class, 'noShow']);
        $router->post('/panel/queue/{id}/cancel', [QueueController::class, 'cancel']);
        $router->post('/panel/pay/{id}', [PaymentController::class, 'store']);
    });

    // --- Staff (owner/manager only) ---------------------------------------
    $router->group(['middleware' => [OwnerManagerRequired::class]], function ($router) {
        $router->get('/panel/staff', [StaffController::class, 'index']);
        $router->get('/panel/staff/create', [StaffController::class, 'create']);
        $router->get('/panel/staff/{id}/edit', [StaffController::class, 'edit']);

        $router->group(['middleware' => [VerifyCsrf::class]], function ($router) {
            $router->post('/panel/staff', [StaffController::class, 'store']);
            $router->post('/panel/staff/{id}', [StaffController::class, 'update']);
            $router->post('/panel/staff/{id}/toggle', [StaffController::class, 'toggle']);
        });

        // --- Services --------------------------------------------------------
        $router->get('/panel/services', [ServiceController::class, 'index']);
        $router->get('/panel/services/create', [ServiceController::class, 'create']);
        $router->get('/panel/services/{id}/edit', [ServiceController::class, 'edit']);

        $router->group(['middleware' => [VerifyCsrf::class]], function ($router) {
            $router->post('/panel/services', [ServiceController::class, 'store']);
            $router->post('/panel/services/{id}', [ServiceController::class, 'update']);
            $router->post('/panel/services/{id}/override', [ServiceController::class, 'setOverride']);
            $router->post('/panel/services/{id}/toggle', [ServiceController::class, 'toggle']);
        });

        // --- Reports -------------------------------------------------------
        $router->get('/panel/reports', [ReportController::class, 'daily']);
        $router->get('/panel/reports/monthly', [ReportController::class, 'monthly']);

        // --- Salon settings ----------------------------------------------
        $router->get('/panel/settings', [SalonSettingsController::class, 'show']);
        $router->group(['middleware' => [VerifyCsrf::class]], function ($router) {
            $router->post('/panel/settings/profile', [SalonSettingsController::class, 'updateProfile']);
            $router->post('/panel/settings/hours', [SalonSettingsController::class, 'updateHours']);
            $router->post('/panel/settings/holidays/seed', [SalonSettingsController::class, 'seedHolidays']);
            $router->post('/panel/settings/holidays', [SalonSettingsController::class, 'addHoliday']);
            $router->post('/panel/settings/holidays/{id}/remove', [SalonSettingsController::class, 'removeHoliday']);
        });
    });
});
