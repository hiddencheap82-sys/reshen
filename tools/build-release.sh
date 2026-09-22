#!/usr/bin/env bash
# ساخت بستهٔ نصب برای هاست cPanel.
#
# چرا: مشتری Composer و Node ندارد. این اسکریپت همه‌چیزِ لازم را در یک
# zip می‌گذارد که فقط باید آپلود و اکسترکت شود.
#
#   ./tools/build-release.sh          → dist/reshen-<نسخه>.zip
set -euo pipefail

cd "$(dirname "$0")/.."
ROOT=$(pwd)
VERSION=$(git describe --tags --always 2>/dev/null || echo "dev")
OUT="$ROOT/dist"
STAGE="$OUT/reshen"

rm -rf "$STAGE" && mkdir -p "$STAGE"

echo "→ ساخت دارایی‌ها"
if [ ! -f public/assets/css/app.css ]; then
    echo "   app.css نیست — tools/assets/build.sh را اجرا کنید" >&2
    exit 1
fi

echo "→ وابستگی‌های زمان‌اجرا (بدون ابزار توسعه)"
composer install --no-dev --prefer-dist --optimize-autoloader --quiet

echo "→ سنجش نسخهٔ PHP بسته"
# نگهبان: آیا وابستگی‌ها نسخه‌ای بالاتر از چیزی که ادعا می‌کنیم می‌خواهند؟
#
# یک بار endroid/qr-code نسخهٔ ۶ نصب شد، که php ^8.4 می‌خواهد. کامپوزر
# روی این ماشین (۸.۴) با کمال میل قبولش کرد و vendor/composer/platform_check.php
# را طوری ساخت که روی هر چیزی پایین‌تر از ۸.۴ خطای مرگبار بدهد. بسته
# ساخته شد، آپلود شد، و روی هاست مشتری با ۸.۱ اولین صفحه ۵۰۰ داد.
#
# جلوی همان: composer.json حالا config.platform.php را ۸.۱ قفل کرده،
# و این خط اگر باز هم چیزی بالاتر خواست، بیلد را می‌خواباند.
MIN_PHP=$(php -r '$c=json_decode(file_get_contents("composer.json"),true);
    preg_match("/(\d+)\.(\d+)/", $c["require"]["php"] ?? ">=8.1", $m);
    printf("%d%02d00", $m[1] ?? 8, $m[2] ?? 1);')
CHECK="vendor/composer/platform_check.php"
if [ -f "$CHECK" ]; then
    WANTED=$(grep -oE 'PHP_VERSION_ID >= [0-9]+' "$CHECK" | grep -oE '[0-9]+$' | sort -rn | head -1)
    if [ -n "${WANTED:-}" ] && [ "$WANTED" -gt "$MIN_PHP" ]; then
        echo "   وابستگی‌ها PHP $WANTED می‌خواهند ولی بسته $MIN_PHP را ادعا می‌کند." >&2
        echo "   یعنی روی هاست مشتری خطای مرگبار می‌دهد. بیلد متوقف شد." >&2
        echo "   چاره: پکیجِ پرتوقع را پایین بیاورید (composer why-not php $MIN_PHP)." >&2
        exit 1
    fi
fi
echo "   وابستگی‌ها با PHP ${MIN_PHP} سازگارند."

# سینتکس خودِ کد هم — اگر ابزارش در دسترس باشد. `php -l` اینجا بی‌فایده
# است: با مفسر همین ماشین می‌سنجد، نه با نسخهٔ هاست.
if [ -x "${RESHEN_COMPAT_HOME:-$HOME/.cache/reshen-phpcompat}/vendor/bin/phpcs" ]; then
    ./tools/check-php-compat.sh
else
    echo "   (سینتکس کد سنجیده نشد — ./tools/check-php-compat.sh را جدا بزنید)"
fi

echo "→ کپی فایل‌ها"
for item in app config database public resources routes tools .htaccess .env.example composer.json; do
    cp -r "$item" "$STAGE/"
done
[ -d vendor ] && cp -r vendor "$STAGE/"

# پوشه‌های نوشتنی، خالی ولی موجود
mkdir -p "$STAGE/storage/logs" "$STAGE/storage/uploads/customer_photos"
# پوشهٔ لوگو باید در بسته باشد: روی هاست سخت‌گیر، PHP اجازهٔ ساختن پوشه
# داخل public را ندارد و آپلود لوگو بی‌دلیل شکست می‌خورد.
mkdir -p "$STAGE/public/uploads/logos"
cp storage/.htaccess "$STAGE/storage/" 2>/dev/null || true
touch "$STAGE/storage/logs/.gitkeep" "$STAGE/storage/uploads/customer_photos/.gitkeep"
touch "$STAGE/public/uploads/logos/.gitkeep"

echo "→ حذف چیزهایی که مشتری لازم ندارد"
rm -rf "$STAGE/tools/assets/node_modules" "$STAGE/tools/assets" "$STAGE/tools/build-release.sh"
find "$STAGE" -name '.DS_Store' -delete 2>/dev/null || true

# هرس vendor.
#
# اگر کامپوزر پکیجی را از سورس گرفته باشد، پوشهٔ .git آن هم می‌آید —
# برای یک کتابخانهٔ QR این یعنی ۱۵ مگابایت پکِ گیت در بسته‌ای که باید
# روی هاست اشتراکی آپلود شود. تستِ خودِ کتابخانه‌ها هم روی هاست
# مشتری کاری نمی‌کند.
find "$STAGE/vendor" -type d \( -name '.git' -o -name '.github' -o -name 'tests' \
    -o -name 'test' -o -name 'doc' -o -name 'docs' -o -name 'examples' \) \
    -prune -exec rm -rf {} + 2>/dev/null || true
# فونت‌های کتابخانهٔ QR: ۱۶ مگابایت برای متنی که زیر QR چاپ شود.
# رشن هیچ برچسبی زیر QR نمی‌گذارد (QrCodeService مستقیم به نویسنده
# می‌دهد و اصلاً سراغ Label نمی‌رود)، پس این‌ها بار اضافه‌اند روی
# فایلی که مشتری باید با اینترنت خانگی روی هاست اشتراکی آپلود کند.
#
# شرطِ زیر عمدی است: اگر روزی کد واقعاً برچسب بگذارد، فونت‌ها باید
# بمانند وگرنه QR روی هاست مشتری خطا می‌دهد.
if grep -rqs 'labelText\|labelFont\|LabelInterface' "$STAGE/app"; then
    echo "   (فونت‌های QR نگه داشته شدند — کد از برچسب استفاده می‌کند)"
else
    rm -rf "$STAGE/vendor/endroid/qr-code/assets"
fi

find "$STAGE/vendor" -type f \( -name '.gitignore' -o -name '.gitattributes' \
    -o -name 'phpunit.xml*' -o -name '*.dist' -o -name 'Makefile' \) \
    -delete 2>/dev/null || true

# راهنمای کوتاه کنار بسته
cp docs/40-deploy/01-cpanel.md "$STAGE/نصب.md" 2>/dev/null || true

echo "→ فشرده‌سازی"
cd "$OUT"
ZIP="reshen-${VERSION}.zip"
rm -f "$ZIP"
zip -qr "$ZIP" reshen
rm -rf "$STAGE"

echo
echo "آماده: dist/$ZIP  ($(du -h "$ZIP" | cut -f1))"
echo
echo "یادآوری: بعد از این، composer install را دوباره بزنید تا"
echo "ابزارهای توسعه (PHPUnit) برگردند."
