#!/usr/bin/env bash
# بازتولید CSS و فونت‌ها. بعد از هر تغییر در کلاس‌های تیلویندِ ویوها اجرا شود.
#
# چرا دستی و نه خودکار: خروجی داخل ریپو کامیت می‌شود (تصمیم ت-۰۳) تا هاست
# cPanel هیچ مرحلهٔ ساختی نداشته باشد. پس ساخت، کار توسعه‌دهنده است نه سرور.
set -euo pipefail
cd "$(dirname "$0")"
ROOT=$(cd ../.. && pwd)

npm install --no-audit --no-fund --silent tailwindcss@3 vazirmatn
npx tailwindcss -c tailwind.config.js -i src.css -o "$ROOT/public/assets/css/app.css" --minify
cp node_modules/vazirmatn/fonts/webfonts/Vazirmatn-Regular.woff2 "$ROOT/public/assets/fonts/"
cp node_modules/vazirmatn/fonts/webfonts/Vazirmatn-Bold.woff2    "$ROOT/public/assets/fonts/"

echo "ساخته شد: public/assets/css/app.css ($(du -h "$ROOT/public/assets/css/app.css" | cut -f1))"
