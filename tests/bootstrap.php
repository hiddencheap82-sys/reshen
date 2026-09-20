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

/*
 * حفاظ: تست‌های Feature جدول‌ها را خالی می‌کنند.
 *
 * اگر به اشتباه روی دیتابیس توسعه یا — بدتر — تولید اجرا شوند، دادهٔ
 * واقعی را می‌برند. این اتفاق یک بار در همین پروژه افتاد و دادهٔ
 * نمایشی پاک شد.
 *
 * پس: نام دیتابیس باید «test» داشته باشد. برای اجرای تست‌ها یک
 * ‎.env.testing‎ بساز که DB_DATABASE آن مثلاً reshen_test باشد.
 */
$database = (string) App\Core\Config::get('database.database', '');

if (!str_contains($database, 'test')) {
    fwrite(STDERR, "\n\033[31mتست‌ها متوقف شدند.\033[0m\n");
    fwrite(STDERR, "دیتابیس «{$database}» نام «test» ندارد و تست‌های Feature جدول‌هایش را خالی می‌کنند.\n");
    fwrite(STDERR, "یک .env.testing بساز با DB_DATABASE=reshen_test و دوباره اجرا کن.\n\n");
    exit(1);
}

/*
 * اسکیمای دیتابیس تست را خودش می‌سازد.
 *
 * چرا: قبلاً ساختن و مهاجرت دادنِ reshen_test کارِ دستی بود، و
 * tools/migrate.php هم ‎.env‎ را می‌خواند نه ‎.env.testing‎ را — یعنی
 * نمی‌شد با آن، دیتابیس تست را مهاجرت داد. نتیجه: هر کس ریپو را
 * می‌گرفت، تست‌هایش با «جدول پیدا نشد» می‌افتاد.
 *
 * چون حفاظ بالا تضمین کرده نام دیتابیس «test» دارد، ساختن و مهاجرت
 * دادنش اینجا بی‌خطر است.
 */
try {
    App\Core\DB::selectOne('SELECT 1 FROM migrations LIMIT 1');
} catch (Throwable) {
    fwrite(STDOUT, "آماده‌سازی اسکیمای «{$database}»...\n");
    (new App\Core\Migrator(BASE_PATH . '/database/migrations'))->run();
}

if ((new App\Core\Migrator(BASE_PATH . '/database/migrations'))->pendingCount() > 0) {
    fwrite(STDOUT, "اعمال مهاجرت‌های جدید روی «{$database}»...\n");
    (new App\Core\Migrator(BASE_PATH . '/database/migrations'))->run();
}
