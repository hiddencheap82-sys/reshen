# ۰۵ — طراحی API

> **وضعیت:** این سند طراحیِ API فاز ۲ است و هنوز ساخته نشده. پیاده‌سازی
> فعلی سمت سرور رندر می‌شود و تنها نقطهٔ JSON موجود `/panel/queue/poll`
> است. نام‌های Sanctum و Reverb از دورهٔ طراحی لاراولی مانده‌اند —
> [ADR-0009](adr/0009-vanilla-php-over-laravel.md).


`REST` · نسخه‌دار زیر `/api/v1` · بدنه‌ها JSON · احراز هویت با **Laravel Sanctum**

> در فاز ۱، پنل سالن با Livewire ساخته می‌شود و مستقیم با دامنه کار می‌کند — API فقط برای صفحهٔ
> مشتری و آمادگی اپ‌های فاز ۳ است. ولی **قرارداد از روز اول نوشته می‌شود**، چون بعداً بازنویسی‌اش
> گران است.

---

## ۱. احراز هویت

ورود فقط با **موبایل + کد یک‌بارمصرف**. ایمیل و رمز وجود ندارد.

```http
POST /api/v1/auth/otp/request
{ "phone": "09121234567" }
→ 200 { "expires_in": 120, "retry_after": 60 }

POST /api/v1/auth/otp/verify
{ "phone": "09121234567", "code": "4821" }
→ 200 { "token": "...", "user": {...}, "salons": [...] }
```

| قاعده | مقدار |
|---|---|
| طول کد | ۴ رقم (۵ رقم برای نقش‌های مدیریتی) |
| اعتبار | ۲ دقیقه |
| فاصلهٔ درخواست مجدد | ۶۰ ثانیه |
| حداکثر تلاش | ۵ بار، بعد قفل ۱۵ دقیقه‌ای |
| محدودسازی نرخ | ۳ در ساعت به ازای شماره، ۱۰ در ساعت به ازای IP |

> کد در لاگ نوشته نمی‌شود و به‌صورت هش ذخیره می‌شود.

**صفحهٔ «نوبت من» اصلاً احراز هویت ندارد** — با `public_token` کار می‌کند. این عمدی است:
مشتری نباید برای دیدن نوبتش وارد شود. توکن ۱۲ کاراکتری تصادفی، `noindex` و پس از ۷ روز از پایان
نوبت منقضی می‌شود.

---

## ۲. عمومی (بدون ورود)

```http
GET  /api/v1/salons/{slug}                     اطلاعات سالن، خدمات، آرایشگرها
GET  /api/v1/salons/{slug}/availability        زمان‌های آزاد
       ?service_ids=1,3&staff_id=5&date=1405-07-02
GET  /api/v1/salons/{slug}/queue               ★ صف زندهٔ عمومی
GET  /api/v1/q/{token}                         ★ وضعیت نوبت من
POST /api/v1/q/{token}/cancel                  لغو توسط مشتری
POST /api/v1/salons/{slug}/appointments        رزرو (پس از تأیید کد)
```

### ★ `GET /salons/{slug}/queue` — پرکاربردترین اندپوینت

```json
{
  "salon": { "name": "آرایشگاه شهاب", "is_open": true },
  "server_time": "2026-09-18T14:20:11Z",
  "chairs": [
    {
      "staff_id": 5,
      "name": "رضا",
      "status": "busy",
      "waiting_count": 3,
      "eta_next_free": { "min": 22, "max": 31, "label": "۲۲ تا ۳۱ دقیقه" },
      "accepts_walkins": true
    },
    { "staff_id": 6, "name": "حسن", "status": "free", "waiting_count": 0,
      "eta_next_free": { "min": 0, "max": 5, "label": "الان آزاد است" },
      "accepts_walkins": true }
  ]
}
```

**نام مشتریان هرگز در این پاسخ نیست** — فقط تعداد. صفحهٔ عمومی است.

### ★ `GET /q/{token}` — صفحهٔ نوبت من

```json
{
  "status": "queued",
  "kind": "walkin",
  "salon": { "name": "آرایشگاه شهاب", "slug": "shahab",
             "phone": "02133445566", "address": "..." },
  "staff": { "name": "رضا" },
  "services": [{ "name": "اصلاح مو", "price": 3000000 }],
  "position": 2,
  "ahead_count": 2,
  "eta": {
    "min_at": "2026-09-18T14:45:00Z",
    "max_at": "2026-09-18T14:58:00Z",
    "label": "۲۵ تا ۳۸ دقیقهٔ دیگر",
    "confidence": "high"
  },
  "can_cancel": true,
  "poll_after_seconds": 15
}
```

`confidence` سه مقدار دارد: `high` (آمار کافی)، `medium`، `low` (کمتر از ۸ نمونه). رابط کاربری
با `low` جملهٔ محتاطانه‌تری نشان می‌دهد.

**پولینگ با ETag:**
```http
GET /api/v1/q/abc123xyz789
If-None-Match: "v7-1758204011"
→ 304 Not Modified          (اکثر درخواست‌ها)
```

---

## ۳. پنل سالن (نیازمند ورود + `X-Salon-Id`)

### صف — قلب پنل
```http
GET   /api/v1/panel/queue                      صف کامل با نام‌ها
POST  /api/v1/panel/queue/walkin               ★ افزودن حضوری (یک ضربه)
POST  /api/v1/panel/appointments/{id}/start    شروع خدمت
POST  /api/v1/panel/appointments/{id}/complete ★ پایان + تسویه
POST  /api/v1/panel/appointments/{id}/no-show
POST  /api/v1/panel/appointments/{id}/skip     رد کردن (به انتهای صف)
PATCH /api/v1/panel/queue/reorder              جابه‌جایی دستی
```

**`POST /panel/queue/walkin` — باید زیر ۳۰۰ میلی‌ثانیه باشد:**
```json
// درخواست — حداقلی‌ترین شکل ممکن
{ "service_ids": [1], "staff_id": 5, "phone": "09121234567" }

// پاسخ — بلافاصله قابل نمایش به مشتری
{ "appointment_id": 8821, "public_token": "k3m9x2p7q1zt",
  "position": 3,
  "eta": { "label": "۳۵ تا ۴۵ دقیقه", "min_at": "...", "max_at": "..." },
  "sms_sent": true }
```

`phone` اختیاری است. بدون آن، مشتری در صف هست ولی پیامک نمی‌گیرد.

**`POST /appointments/{id}/complete` — دو کار در یک درخواست:**
```json
{ "payments": [{ "method": "cash", "amount": 3000000 }],
  "tip_amount": 200000,
  "products": [{ "product_id": 4, "quantity": 1, "price": 850000 }] }
```
> پایان خدمت و تسویه **یک عمل‌اند**. جدا کردنشان یعنی آرایشگر یکی را می‌زند و دیگری را نه — و
> [R1](../00-product/09-risks.md) اتفاق می‌افتد.

### بقیه
```http
GET|POST|PATCH   /api/v1/panel/appointments
GET|POST|PATCH   /api/v1/panel/customers
GET              /api/v1/panel/customers/{id}/history
PUT              /api/v1/panel/customers/{id}/preferences   دفترچهٔ آرایشگر
POST             /api/v1/panel/customers/{id}/photos
GET|POST|PATCH   /api/v1/panel/services
GET|POST|PATCH   /api/v1/panel/staff
PUT              /api/v1/panel/staff/{id}/working-hours
POST             /api/v1/panel/time-offs
GET              /api/v1/panel/reports/daily
GET              /api/v1/panel/reports/staff/{id}/today     ★ «امروز چقدر درآوردی»
GET              /api/v1/panel/payouts                      فاز ۲
POST             /api/v1/panel/payouts/{id}/finalize
GET|POST         /api/v1/panel/waitlist                     فاز ۲
GET              /api/v1/panel/sms/balance
POST             /api/v1/panel/sms/topup
```

---

## ۴. پلتفرم (فقط `platform_admin`)

```http
GET    /api/v1/platform/salons
POST   /api/v1/platform/salons/{id}/impersonate   ← همیشه در audit_log
GET    /api/v1/platform/metrics                   ★ شامل MAE تخمین
GET    /api/v1/platform/salons/at-risk            سالن‌های در خطر ریزش
```

---

## ۵. قراردادهای عمومی

### خطاها
```json
{
  "error": {
    "code": "slot_taken",
    "message": "این زمان همین الان پر شد. چند گزینهٔ نزدیک پیشنهاد می‌کنیم.",
    "alternatives": [{ "at": "...", "label": "۱۸:۳۰" }]
  }
}
```
`message` **همیشه فارسی و برای نمایش مستقیم** است. `code` برای کد. هیچ پیام خطای انگلیسی به
کاربر نمی‌رسد.

| کد | HTTP |
|---|---|
| `validation_failed` | 422 |
| `slot_taken` | 409 |
| `salon_closed` | 409 |
| `otp_throttled` | 429 |
| `sms_balance_empty` | 402 |
| `trust_score_requires_deposit` | 402 |
| `salon_suspended` | 403 |

### تاریخ و ساعت
- همهٔ ورودی/خروجی‌های API در **UTC** و به فرمت ISO 8601
- تبدیل شمسی فقط در رابط کاربری
- **استثنا:** پارامتر `date` در `availability` شمسی است (`1405-07-02`) چون مستقیماً از تقویم UI می‌آید

### پول
همه‌جا **ریال** و عدد صحیح. `3000000` یعنی ۳۰۰ هزار تومان.

### محدودسازی نرخ

| گروه | سقف |
|---|---|
| عمومیِ خواندنی | ۶۰ در دقیقه به ازای IP |
| `GET /q/{token}` | ۱۲۰ در دقیقه (پولینگ) |
| درخواست کد | ۳ در ساعت به ازای شماره |
| رزرو | ۵ در ساعت به ازای شماره |
| پنل | ۳۰۰ در دقیقه به ازای کاربر |

### صفحه‌بندی
```
?page=2&per_page=25        (حداکثر ۱۰۰)
→ { "data": [...], "meta": { "page", "per_page", "total", "last_page" } }
```

### ایدمپوتنسی
`POST`های پولی (`complete`, `topup`, پرداخت) هدر `Idempotency-Key` می‌پذیرند. اجباری برای اپ
موبایل، چون شبکهٔ سالن قطع و وصل می‌شود و تسویهٔ دوباره یعنی پول دو بار ثبت شده.

---

## ۶. بی‌درنگ

| فاز | روش |
|---|---|
| ۱ | پولینگ هر ۱۵ ثانیه با `ETag` + `poll_after_seconds` در پاسخ (سرور می‌تواند کندش کند) |
| ۲ | **Laravel Reverb** — کانال خصوصی `salon.{id}.queue`، با پولینگ به‌عنوان جایگزین |

سرور با `poll_after_seconds` کنترل بار را در دست دارد: در ساعت اوج می‌تواند به ۳۰ ثانیه ببرد.

رویدادهای Reverb: `queue.recalculated` · `appointment.started` · `appointment.completed` ·
`appointment.created` · `appointment.cancelled`

---

## ۷. وب‌هوک‌های ورودی

```http
POST /webhooks/zibal        نتیجهٔ پرداخت
POST /webhooks/sms/{provider}  وضعیت تحویل پیامک
```
هر دو با امضای HMAC اعتبارسنجی می‌شوند و **هرگز** به IP اعتماد نمی‌کنند.

---

## ۸. فاز ۴ — مارکت‌پلیس

```http
GET /api/v1/discover/salons?city=tehran&district=5&service=haircut
                          &sort=wait_time&lat=..&lng=..
GET /api/v1/discover/salons/{slug}/reviews
GET /api/v1/me/appointments        سابقهٔ مشتری در همهٔ سالن‌ها
```

`sort=wait_time` — همان چیزی که هیچ رقیبی نمی‌تواند داشته باشد، چون دادهٔ لحظه‌ای صف را ندارد.
