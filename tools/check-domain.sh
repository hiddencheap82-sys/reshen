#!/usr/bin/env bash
# آزاد بودن دامنه را بررسی می‌کند — برای انتخاب نام پلتفرم.
#
# چرا اسکریپت و نه بررسی دستی: هر نام را باید روی چند پسوند و چند
# نویسه‌گردانی امتحان کرد (reshen، reshan، …) و این با کلیک کردن در
# سایت ثبت‌کننده‌ها خسته‌کننده و پرخطاست.
#
# روش: whois برای پسوندهای جهانی، و DNS برای .ir.
#
# ⚠ پاسخِ DNS قطعی نیست: دامنه‌ای که رکورد NS ندارد *احتمالاً* آزاد
#   است، ولی ممکن است ثبت‌شده و بی‌استفاده باشد. پیش از خرید، حتماً
#   در whois.nic.ir یا پنل ثبت‌کننده تأیید بگیرید.

set -uo pipefail

NAMES=("$@")
if [ ${#NAMES[@]} -eq 0 ]; then
  NAMES=(reshen noban nobatam pirayesh safban nobatak)
fi

TLDS=(ir com co app)

have() { command -v "$1" >/dev/null 2>&1; }

if ! have whois && ! have dig && ! have host; then
  echo "هیچ‌کدام از whois/dig/host نصب نیستند."
  echo "روی اوبونتو/دبیان:  sudo apt install whois dnsutils"
  echo "روی مک:            brew install whois bind"
  exit 1
fi

# «آزاد» را چطور تشخیص می‌دهیم: عبارت‌هایی که ثبت‌کننده‌ها برای
# دامنهٔ ثبت‌نشده برمی‌گردانند.
# هر ثبت‌کننده جملهٔ خودش را برای «ثبت نشده» دارد؛ این‌ها رایج‌ترین‌هایند.
FREE_RE='No match|NOT FOUND|No Data Found|no entries found|Status: *free|is available|No such domain|Domain not found|does not exist|has not been registered|Status: *AVAILABLE'

check_whois() {
  local d="$1" out
  out=$(whois "$d" 2>/dev/null)
  if [ -z "$out" ]; then echo "؟ نامشخص"; return; fi
  if echo "$out" | grep -qEi "$FREE_RE"; then echo "✓ آزاد"; else echo "✗ گرفته"; fi
}

check_dns() {
  local d="$1" ns=""
  if have dig; then ns=$(dig +short NS "$d" 2>/dev/null)
  elif have host; then ns=$(host -t NS "$d" 2>/dev/null | grep -v "not found" || true)
  fi
  if [ -z "$ns" ]; then echo "؟ شاید آزاد"; else echo "✗ گرفته"; fi
}

printf '%-12s' "نام"
for t in "${TLDS[@]}"; do printf '%-14s' ".$t"; done
echo

for n in "${NAMES[@]}"; do
  printf '%-12s' "$n"
  for t in "${TLDS[@]}"; do
    d="$n.$t"
    if [ "$t" = "ir" ]; then
      # nic.ir به whoisِ استاندارد جواب نمی‌دهد؛ DNS تخمین می‌زند.
      r=$(check_dns "$d")
    else
      r=$(have whois && check_whois "$d" || check_dns "$d")
    fi
    printf '%-14s' "$r"
  done
  echo
done

cat <<'NOTE'

— «؟ شاید آزاد» یعنی رکورد DNS ندارد. برای .ir حتماً در
  https://www.nic.ir  جست‌وجو کنید؛ تنها مرجع قطعی همان است.
— برای .com و بقیه، نتیجهٔ whois قابل اتکاست.
NOTE
