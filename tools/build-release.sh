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

echo "→ کپی فایل‌ها"
for item in app config database public resources routes tools .htaccess .env.example composer.json; do
    cp -r "$item" "$STAGE/"
done
[ -d vendor ] && cp -r vendor "$STAGE/"

# پوشه‌های نوشتنی، خالی ولی موجود
mkdir -p "$STAGE/storage/logs" "$STAGE/storage/uploads/customer_photos"
cp storage/.htaccess "$STAGE/storage/" 2>/dev/null || true
touch "$STAGE/storage/logs/.gitkeep" "$STAGE/storage/uploads/customer_photos/.gitkeep"

echo "→ حذف چیزهایی که مشتری لازم ندارد"
rm -rf "$STAGE/tools/assets/node_modules" "$STAGE/tools/assets" "$STAGE/tools/build-release.sh"
find "$STAGE" -name '.DS_Store' -delete 2>/dev/null || true

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
