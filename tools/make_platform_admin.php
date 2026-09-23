<?php
/**
 * ارتقای یک شماره به مدیر پلتفرم، از خط فرمان.
 *
 * راه *اصلی* دیگر این نیست: نصاب در گام سوم اولین مدیر کل را با رمز
 * می‌سازد، و بعد از آن از پنل مدیریت کل می‌شود مدیر تازه اضافه کرد.
 * هر دو از مرورگر کار می‌کنند و به SSH نیاز ندارند — که روی هاست
 * اشتراکی ایرانی معمولاً نیست.
 *
 * این فایل برای وقتی می‌ماند که به پنل دسترسی ندارید: تنها مدیر کل
 * را از دست داده‌اید، یا دارید روی سروری کار می‌کنید که هنوز کسی
 * رویش وارد نشده.
 */
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\DB;
use App\Support\IranMobile;

$phoneArg = $argv[1] ?? null;
if ($phoneArg === null) {
    fwrite(STDERR, "Usage: php tools/make_platform_admin.php <phone>\n");
    exit(1);
}

$phone = IranMobile::parse($phoneArg);
$user = DB::selectOne('SELECT id FROM users WHERE phone = ?', [$phone->e164]);
if ($user === null) {
    fwrite(STDERR, "No user with that phone yet — they must log in once first.\n");
    exit(1);
}

DB::update('users', ['is_platform_admin' => 1], 'id = :id', ['id' => $user['id']]);
echo "OK — {$phone->e164} is now a platform admin.\n";
