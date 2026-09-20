<?php

declare(strict_types=1);

use App\Core\Request;
use App\Core\Router;

require dirname(__DIR__) . '/app/bootstrap.php';

$router = new Router();
require dirname(__DIR__) . '/routes/web.php';

$request = new Request();
$response = $router->dispatch($request);
$response->send();
