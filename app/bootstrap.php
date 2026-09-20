<?php

declare(strict_types=1);

use App\Core\Autoloader;
use App\Core\Config;
use App\Core\Env;
use App\Core\Session;
use App\Core\View;

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/app/Core/Autoloader.php';
Autoloader::register('App', BASE_PATH . '/app');

Env::load(BASE_PATH . '/.env');
Config::load(BASE_PATH . '/config');
require BASE_PATH . '/app/Support/helpers.php';

date_default_timezone_set((string) Config::get('app.timezone', 'Asia/Tehran'));

View::setBasePath(BASE_PATH . '/resources/views');

Session::start();

if (Config::get('app.debug')) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}

set_exception_handler(static function (Throwable $e): void {
    http_response_code(500);
    error_log($e->getMessage() . "\n" . $e->getTraceAsString());
    if (Config::get('app.debug')) {
        echo '<pre style="direction:ltr;text-align:left;padding:2rem;background:#1e1e1e;color:#f66">';
        echo htmlspecialchars($e->getMessage() . "\n\n" . $e->getTraceAsString());
        echo '</pre>';
    } else {
        echo '<h1 style="font-family:sans-serif;text-align:center;padding:4rem">خطایی رخ داد. لطفاً دوباره تلاش کنید.</h1>';
    }
});
