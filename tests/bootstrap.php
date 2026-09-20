<?php

declare(strict_types=1);

/**
 * بوت‌استرپ تست‌ها.
 *
 * چرا جدا از app/bootstrap.php: آن فایل Session::start() صدا می‌زند و
 * هدر می‌فرستد، که در CLI خطا می‌دهد. اینجا فقط چیزهایی را بالا می‌آوریم
 * که تست لازم دارد: اتولودر، پیکربندی، و هلپرها.
 */

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/app/Core/Autoloader.php';
App\Core\Autoloader::register('App', BASE_PATH . '/app');
App\Core\Autoloader::register('Tests', BASE_PATH . '/tests');

// .env.testing اگر بود، وگرنه .env
$envFile = is_file(BASE_PATH . '/.env.testing') ? '/.env.testing' : '/.env';
App\Core\Env::load(BASE_PATH . $envFile);
App\Core\Config::load(BASE_PATH . '/config');

require BASE_PATH . '/app/Support/helpers.php';

date_default_timezone_set((string) App\Core\Config::get('app.timezone', 'Asia/Tehran'));
