# ۰۱ — ساختار ریپو

```
reshen/
├── README.md
├── composer.json
├── package.json
├── docker-compose.yml
├── Makefile
├── .env.example
│
├── app/
│   ├── Domain/                  ★ منطق کسب‌وکار — قلب پروژه
│   │   ├── Tenancy/             چندمستأجری، اسکوپ، عدم‌واسطه‌گری
│   │   ├── Identity/            کاربر، ورود با OTP، نقش‌ها
│   │   ├── Salon/               سالن، شعبه، ساعت کاری، تعطیلی
│   │   ├── Staff/               آرایشگر، خدماتش، مرخصی، جبران خدمت
│   │   ├── Catalog/             خدمات، قیمت، محصولات
│   │   ├── Customer/            پرونده، دفترچهٔ آرایشگر، عکس، اعتبار
│   │   ├── Booking/             رزرو، زمان‌های آزاد، لغو، لیست انتظار
│   │   ├── Queue/               ★★ صف زنده و موتور ETA
│   │   ├── Payment/             پرداخت، بیعانه، درگاه
│   │   ├── Payout/              تسویهٔ صندلی
│   │   ├── Loyalty/             کارت امتیاز، باشگاه، اشتراک
│   │   ├── Messaging/           پیامک، الگوها، کیف
│   │   ├── Reporting/           گزارش‌ها، شاخص‌ها، «نجات‌یافته‌ها»
│   │   └── Platform/            اشتراک و صورتحساب خودمان
│   │
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Api/V1/          Public/ Panel/ Platform/
│   │   │   └── Web/             Booking/ Panel/
│   │   ├── Livewire/            Panel/ (Queue/ Customers/ ...)
│   │   ├── Middleware/          ResolveSalon, EnsureRole, ...
│   │   ├── Requests/
│   │   └── Resources/
│   │
│   ├── Support/
│   │   ├── Jalali/              تقویم شمسی، تعطیلات، اعداد فارسی
│   │   ├── Money/               Money value object (ریال)
│   │   ├── Phone/               IranMobile value object
│   │   └── Http/                ETag، محدودسازی نرخ
│   │
│   ├── Console/Commands/        reshen:doctor, reshen:recompute-stats, ...
│   └── Providers/
│
├── config/reshen.php            تنظیمات محصول (بافرها، سقف‌ها، پنجرهٔ حق تقدم)
├── database/migrations/ seeders/ factories/
├── lang/fa/
├── resources/
│   ├── views/  (panel/ booking/ queue/ components/ layouts/)
│   ├── js/     (app.js  pwa/service-worker.js  queue-poller.js)
│   └── css/
├── routes/     (web.php api.php panel.php booking.php console.php)
├── tests/      (Unit/ Feature/ Architecture/)
├── tools/      host-check.php  ← موجود
└── docs/
```

---

## چرا `Domain/`

منطق کسب‌وکار در پوشهٔ دامنه است، نه در کنترلر و مدل. سه دلیل:

1. **موتور صف پیچیده است** و باید بدون HTTP قابل تست باشد
2. **تسویه پول واقعی است** — منطقش باید یک‌جا و قابل بازبینی باشد
3. **فاز ۴ ممکن است جدا شود** — مرز از قبل کشیده باشد

### آناتومی یک دامنه

```
app/Domain/Queue/
├── Models/            Appointment, DurationStat
├── Actions/           ★ واحد کار — یک کلاس، یک متد عمومی
│   ├── AddWalkIn.php
│   ├── StartService.php
│   ├── CompleteService.php
│   ├── RecalculateQueue.php
│   └── RecordActualDuration.php
├── Services/          EtaCalculator, QueueOrderer, DurationEstimator
├── DTOs/              QueueSnapshot, EtaEstimate, ChairState
├── Events/            QueueRecalculated, ServiceStarted, ServiceCompleted
├── Listeners/         NotifyNearlyUp, NotifyDelay, UpdateDurationStats
├── Enums/             AppointmentKind, AppointmentStatus
├── Policies/
└── Exceptions/        ChairBusyException, SalonClosedException
```

### قواعدی که شکسته نمی‌شوند

| قاعده | چرا |
|---|---|
| کنترلر فقط اعتبارسنجی می‌کند و یک `Action` صدا می‌زند | منطق در کنترلر، غیرقابل تست و غیرقابل استفادهٔ مجدد است |
| هیچ منطق کسب‌وکاری در مدل، کنترلر یا کامپوننت Livewire | |
| `Action` جهش می‌دهد، `Service` محاسبه می‌کند | تفکیک روشن خواندن و نوشتن |
| دامنه‌ها از طریق **رویداد** با هم حرف می‌زنند، نه صدا زدن مستقیم | `Queue` نباید بداند `Messaging` وجود دارد |
| هر مدل مستأجری، `BelongsToSalon` دارد | تست معماری اجبارش می‌کند |

**مثال جریان — «تمام شد»:**
```
CompleteService (Action)
  ├─ actual_end_at را ثبت می‌کند
  ├─ پرداخت را ثبت می‌کند
  ├─ رویداد ServiceCompleted منتشر می‌کند
  │    ├─▶ UpdateDurationStats   (Queue)      آمار را تغذیه می‌کند
  │    ├─▶ RecalculateQueue      (Queue)      ETA بقیه را به‌روز می‌کند
  │    ├─▶ NotifyNearlyUp        (Messaging)  پیامک به نفر سوم
  │    └─▶ UpdateCustomerStats   (Customer)   تعداد مراجعه، آخرین بازدید
  └─ QueueSnapshot برمی‌گرداند
```

`Queue` هیچ‌جا `SmsProvider` را صدا نمی‌زند. این جداسازی یعنی تغییر ارائه‌دهندهٔ پیامک، هیچ فایلی
در `Queue` را لمس نمی‌کند.

---

## مسیرها

| فایل | پیشوند | محافظ |
|---|---|---|
| `web.php` | `/` | — |
| `booking.php` | `/s/{slug}`, `/q/{token}` | — (عمومی) |
| `panel.php` | `/panel` | `auth`, `salon` |
| `api.php` | `/api/v1` | `sanctum` (جز عمومی‌ها) |

## `config/reshen.php`

همهٔ اعداد جادویی یک‌جا، نه پراکنده در کد:

```php
return [
    'queue' => [
        'priority_window_minutes' => 10,   // پنجرهٔ حق تقدم رزروشده
        'turnover_buffer_minutes' => 5,    // بافر بین دو مشتری
        'multi_service_buffer'    => 2,    // بافر بین دو خدمت
        'min_remaining_minutes'   => 2,    // هرگز نگو «همین الان»
        'recalculate_every_secs'  => 90,
        'max_range_minutes'       => 25,   // سقف بازهٔ نمایشی
    ],
    'eta' => [
        'min_samples'          => 8,       // زیر این، از مدت اسمی استفاده کن
        'sample_window'        => 200,     // پنجرهٔ متحرک
        'customer_factor_min'  => 0.7,
        'customer_factor_max'  => 1.5,
        'outlier_min_minutes'  => 5,
        'outlier_max_minutes'  => 180,
    ],
    'sms' => [
        'max_per_appointment' => 4,
        'quiet_hours'         => ['23:00', '08:00'],
        'emergency_credit'    => -100,     // اجازهٔ موجودی منفی
    ],
];
```

هر یک از این اعداد در [۰۴ — موتور صف و ETA](../10-architecture/04-queue-eta-engine.md) توضیح داده شده.

## تست‌ها

```
tests/
├── Architecture/        ← در CI اجباری
│   ├── TenancyTest.php          هر مدل salon_id دار، trait دارد
│   ├── DomainBoundariesTest.php دامنه‌ها مستقیم هم را صدا نمی‌زنند
│   └── NoBusinessLogicInControllersTest.php
├── Unit/Domain/Queue/   ★ بیشترین پوشش اینجا
└── Feature/
```

**هدف پوشش:** `Domain/Queue` و `Domain/Payout` بالای ۹۰٪. بقیه ۶۰٪ کافی است.
