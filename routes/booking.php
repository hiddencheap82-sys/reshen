<?php

declare(strict_types=1);

use App\Http\Controllers\BookingWizardController;
use App\Http\Controllers\MyAppointmentController;
use App\Http\Middleware\VerifyCsrf;

/** @var \App\Core\Router $router */

$router->get('/s/{slug}', [BookingWizardController::class, 'landing']);
$router->get('/s/{slug}/services', [BookingWizardController::class, 'services']);
$router->get('/s/{slug}/staff', [BookingWizardController::class, 'staffStep']);
$router->get('/s/{slug}/slots', [BookingWizardController::class, 'slotsStep']);
$router->get('/s/{slug}/phone', [BookingWizardController::class, 'phoneStep']);
$router->get('/s/{slug}/verify', [BookingWizardController::class, 'verifyStep']);

$router->get('/q/{token}', [MyAppointmentController::class, 'show']);

$router->group(['middleware' => [VerifyCsrf::class]], function ($router) {
    $router->post('/s/{slug}', [BookingWizardController::class, 'chooseServices']);
    $router->post('/s/{slug}/staff', [BookingWizardController::class, 'staffStep']);
    $router->post('/s/{slug}/slots', [BookingWizardController::class, 'slotsStep']);
    $router->post('/s/{slug}/phone', [BookingWizardController::class, 'phoneStep']);
    $router->post('/s/{slug}/verify', [BookingWizardController::class, 'verifyStep']);
    $router->post('/q/{token}/cancel', [MyAppointmentController::class, 'cancel']);
});
