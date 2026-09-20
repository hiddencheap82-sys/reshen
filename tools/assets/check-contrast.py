#!/usr/bin/env python3
"""
سنجش کنتراست پالت‌ها بر پایهٔ WCAG 2.1.

چرا اسکریپت و نه چشم: رنگی که «خوب به نظر می‌رسد» ممکن است روی موبایل
زیر آفتاب خوانده نشود. آستانهٔ ۴٫۵ برای متن عادی و ۳٫۰ برای متن درشت
و اجزای رابط، عدد است نه سلیقه.
"""
import json, sys, pathlib

def lum(hexcolor):
    h = hexcolor.lstrip('#')
    ch = []
    for i in (0, 2, 4):
        c = int(h[i:i+2], 16) / 255
        ch.append(c / 12.92 if c <= 0.04045 else ((c + 0.055) / 1.055) ** 2.4)
    return 0.2126*ch[0] + 0.7152*ch[1] + 0.0722*ch[2]

def ratio(a, b):
    la, lb = lum(a), lum(b)
    hi, lo = max(la, lb), min(la, lb)
    return (hi + 0.05) / (lo + 0.05)

LIGHT_BG, LIGHT_SURFACE, LIGHT_TEXT = '#FAFAF9', '#FFFFFF', '#1C1917'
DARK_BG,  DARK_SURFACE,  DARK_TEXT  = '#0C0A09', '#1C1917', '#F5F5F4'

palettes = json.loads(pathlib.Path(__file__).with_name('palettes.json').read_text('utf-8'))

fails = []
print(f"{'پالت':<12} {'آزمون':<34} {'نسبت':>6}  وضعیت")
print('─' * 64)

for key, p in palettes.items():
    checks = [
        ('متن روی دکمهٔ اصلی',        p['onAccent'], p['accent'],    4.5),
        ('متن روی دکمه (هاور)',       p['onAccent'], p['accentHi'],  4.5),
        ('رنگ تأکید روی سطح روشن',    p['accent'],   LIGHT_SURFACE,  4.5),
        ('رنگ تأکید روی زمینهٔ روشن', p['accent'],   LIGHT_BG,       4.5),
        ('متن روی سطح نرمِ تأکید',    LIGHT_TEXT,    p['soft'],      4.5),
        ('تأکید تیره روی سطح تیره',   p['darkAccent'], DARK_SURFACE, 4.5),
        ('تأکید تیره روی زمینهٔ تیره',p['darkAccent'], DARK_BG,      4.5),
        ('متن روی سطح نرمِ تیره',     DARK_TEXT,     p['darkSoft'],  4.5),
    ]
    for label, fg, bg, need in checks:
        r = ratio(fg, bg)
        ok = r >= need
        if not ok:
            fails.append((p['name'], label, round(r, 2), need))
        print(f"{p['name']:<12} {label:<34} {r:>6.2f}  {'✓' if ok else '✗ کمتر از ' + str(need)}")
    print()

if fails:
    print(f'\n{len(fails)} آزمون رد شد:')
    for n, l, r, need in fails:
        print(f'  ✗ {n} — {l}: {r} (لازم {need})')
    sys.exit(1)

print('همهٔ پالت‌ها از آستانهٔ WCAG AA عبور کردند.')
