# ۰۳ — مدل داده

قرارداد: جدول‌ها جمع و انگلیسی · `salon_id` روی هر جدول مستأجری · زمان‌ها `TIMESTAMP` و **UTC**
در دیتابیس، شمسی فقط در نمایش · پول `BIGINT` به **ریال** (نه اعشار، نه تومان) · حذف نرم روی
جدول‌های تاریخی.

> **چرا ریال و `BIGINT`:** اعشار شناور در محاسبهٔ درصدِ تسویه، خطای انباشتی می‌سازد و سر پول
> با آرایشگر بحث می‌شود. کوچک‌ترین واحد صحیح، تنها انتخاب درست است. نمایش به تومان در لایهٔ رابط کاربری.

---

## ۱. نمودار

```
                    ┌─────────┐
                    │  users  │ (سراسری — یک شماره، یک هویت)
                    └────┬────┘
                         │
         ┌───────────────┼───────────────────┐
         │               │                   │
   ┌─────▼─────┐   ┌─────▼──────┐     ┌──────▼──────┐
   │  salons   │◀──│ salon_user │     │  customers  │
   └─────┬─────┘   │ (نقش‌ها)   │     │ (هر سالن)   │
         │         └────────────┘     └──────┬──────┘
         │                                   │
    ┌────┴────┬──────────┬─────────┐         │
    │         │          │         │         │
┌───▼───┐ ┌───▼────┐ ┌───▼─────┐ ┌─▼──────┐  │
│ staff │ │services│ │ products│ │branches│  │
└───┬───┘ └───┬────┘ └─────────┘ └────────┘  │
    │         │                              │
    │  ┌──────▼────────┐                     │
    │  │ staff_service │ (مدت و قیمت هر آرایشگر)
    │  └───────────────┘                     │
    │                                        │
    │        ┌───────────────────────────────┘
    │        │
┌───▼────────▼──────────┐       ┌──────────────────┐
│    appointments   ★   │──────▶│appointment_items │
│ (نوبت + حضوری، یکی)   │       └──────────────────┘
└───┬────────────┬──────┘
    │            │
┌───▼──────┐  ┌──▼────────┐  ┌──────────────┐  ┌──────────────┐
│ payments │  │ waitlist  │  │duration_stats│  │ staff_payouts│
└──────────┘  └───────────┘  │  ★ موتور ETA │  └──────────────┘
                             └──────────────┘
```

★ = جدول‌های قلب محصول

---

## ۲. جدول‌های اصلی

### `salons` — مستأجر

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT PK | |
| `slug` | VARCHAR(64) UNIQUE | `reshen.ir/s/{slug}` |
| `name` | VARCHAR(120) | |
| `phone`, `address` | | |
| `city_id`, `district_id` | FK | برای جستجوی فاز ۴ |
| `lat`, `lng` | DECIMAL(10,7) | «نزدیک من» در فاز ۴ |
| `logo_path`, `cover_path` | | |
| `timezone` | default `Asia/Tehran` | |
| `settings` | JSON | بافر بین مشتری، پنجرهٔ حق تقدم، سیاست بیعانه… |
| `plan_id`, `trial_ends_at`, `status` | | `active` / `trial` / `suspended` |
| `sms_balance` | INT | تعداد پیامک باقی‌مانده (می‌تواند تا −۱۰۰ منفی شود) |

### `users` — هویت سراسری
`id` · `phone` **UNIQUE در کل پلتفرم** · `name` · `is_platform_admin` · `last_login_at`

### `salon_user` — نقش‌ها
`salon_id` · `user_id` · `role` ENUM(`owner`,`manager`,`barber`,`receptionist`) · UNIQUE(`salon_id`,`user_id`)

### `staff` — آرایشگر

| ستون | توضیح |
|---|---|
| `salon_id`, `user_id` | |
| `display_name`, `avatar_path`, `bio` | چیزی که مشتری می‌بیند |
| `color` | رنگ ستونش در تقویم |
| `is_active`, `sort_order` | |
| `accepts_walkins` | بعضی آرایشگرها فقط با نوبت کار می‌کنند |
| `compensation_type` | ENUM(`commission`,`booth_rent`,`both`,`salary`) |
| `commission_rate` | DECIMAL(5,2) — درصد |
| `booth_rent_amount`, `booth_rent_period` | |

### `services` — خدمات
`salon_id` · `name` · `category` · `duration_minutes` (اسمی) · `price` (ریال) · `buffer_minutes`
· `is_active` · `requires_deposit` · `deposit_amount` · `online_bookable` · `sort_order`

### `staff_service` — **مهم**
`staff_id` · `service_id` · `duration_minutes` (بازنویسی) · `price` (بازنویسی) · `is_offered`

> آقا رضا فید را ۲۵ دقیقه می‌زند، آقا حسن ۳۵. بدون این جدول، ETA از روز اول غلط است.

---

## ۳. `appointments` — جدول محوری

**تصمیم کلیدی: نوبت رزروشده و مراجعهٔ حضوری، یک جدول‌اند.** جدا کردنشان یعنی دو منطق ترتیب، دو
گزارش و دو باگ. یکی بودنشان، تعریفِ «صف واحد» است.

| ستون | نوع | توضیح |
|---|---|---|
| `id`, `salon_id`, `branch_id` | | |
| `public_token` | CHAR(12) UNIQUE | لینک «نوبت من» بدون رمز — `reshen.ir/q/{token}` |
| `customer_id` | FK nullable | حضوریِ ناشناس می‌تواند null باشد |
| `staff_id` | FK nullable | null = «هر کسی آزاد شد» |
| **`kind`** | ENUM(`booked`,`walkin`) | **رزروشده یا حضوری** |
| `status` | ENUM | `pending`,`confirmed`,`queued`,`in_chair`,`completed`,`cancelled`,`no_show` |
| `source` | ENUM(`online`,`phone`,`walkin`,`staff`,`marketplace`) | |
| `scheduled_at` | TIMESTAMP nullable | زمان وعده‌شده — برای حضوری null |
| `queued_at` | TIMESTAMP nullable | لحظهٔ ورود به صف |
| `queue_position` | INT nullable | جای فعلی در صف |
| **`estimated_start_at`** | TIMESTAMP | ★ خروجی موتور ETA — صدک ۵۰ |
| **`estimated_start_max_at`** | TIMESTAMP | ★ کران بالای بازه — صدک ۸۰ |
| **`actual_start_at`** | TIMESTAMP nullable | ★ ورودی یادگیری |
| **`actual_end_at`** | TIMESTAMP nullable | ★ ورودی یادگیری |
| `expected_duration_minutes` | INT | مجموع تخمین همهٔ خدمات |
| `total_price`, `discount_amount`, `tip_amount` | BIGINT | ریال |
| `deposit_amount`, `deposit_status` | | |
| `notes`, `cancel_reason`, `cancelled_by` | | |
| `confirmed_at`, `reminded_at`, `nearly_up_notified_at` | | جلوگیری از پیامک تکراری |
| `created_by_user_id` | | |

**ایندکس‌ها:**
```sql
INDEX (salon_id, status, scheduled_at)          -- تقویم
INDEX (salon_id, staff_id, status, queued_at)   -- صف زنده  ← داغ‌ترین کوئری
INDEX (salon_id, customer_id, created_at)       -- تاریخچهٔ مشتری
INDEX (salon_id, status, actual_end_at)         -- گزارش و تسویه
UNIQUE (public_token)
```

**`appointment_items`** — چند خدمت در یک نوبت:
`appointment_id` · `service_id` · `staff_id` · `price` · `duration_minutes` · `sort_order`

---

## ۴. `duration_stats` — ★ حافظهٔ موتور ETA

مهم‌ترین جدول تمایز محصول. مشخصات کامل: [۰۴ — موتور صف و ETA](04-queue-eta-engine.md).

| ستون | توضیح |
|---|---|
| `salon_id`, `staff_id`, `service_id` | کلید مرکب |
| `sample_count` | تعداد نمونهٔ معتبر |
| `p50_minutes` | میانه — پایهٔ تخمین |
| `p80_minutes` | صدک ۸۰ — کران بالای بازه |
| `mean_minutes`, `stddev_minutes` | تشخیص ناپایداری |
| `last_computed_at` | |

UNIQUE(`salon_id`,`staff_id`,`service_id`)

و ضریب شخصی مشتری، روی `customers`:
`duration_factor` DECIMAL(3,2) default 1.00 · `duration_samples` INT

---

## ۵. مشتری و دفترچهٔ آرایشگر

### `customers`
`salon_id` · `user_id` nullable · `name` · `phone` · `preferred_staff_id` ·
`visit_count` · `first_visit_at` · `last_visit_at` · `total_spent` ·
`no_show_count` · `late_cancel_count` · **`trust_score`** TINYINT (۰–۱۰۰) ·
`duration_factor` · `tags` JSON · `notes` TEXT · `marketing_opt_in` BOOL

UNIQUE(`salon_id`,`phone`)

### `customer_preferences` — دفترچهٔ آرایشگر (فیچر B03)
`customer_id` · `clipper_sides` (شمارهٔ تیغ کناره) · `clipper_top` · `hair_type` ·
`part_side` (فرق) · `beard_style` · `allergies` · `free_notes`

> این جدول کوچک است ولی **ستون ۲ محصول** است. وقتی رضا قبل از اصلاح یک نگاه می‌اندازد و می‌گوید
> «مثل دفعهٔ قبل، تیغ ۲ کناره؟» — این همان لحظه‌ای است که آرایشگر عاشق محصول می‌شود.

### `customer_photos`
`customer_id` · `appointment_id` · `path` · `kind` ENUM(`before`,`after`,`reference`) ·
`consent_at` — **بدون رضایت ثبت‌شده، عکس ذخیره نمی‌شود**

---

## ۶. پول

### `payments`
`salon_id` · `appointment_id` nullable · `customer_id` · `amount` ·
`method` ENUM(`cash`,`card_to_card`,`pos`,`online`,`credit`,`gift`) ·
`purpose` ENUM(`service`,`deposit`,`product`,`subscription`) ·
`gateway` · `gateway_ref` · `status` · `paid_at` · `recorded_by_user_id`

> `cash` و `card_to_card` روش‌های **درجه‌یک**اند، نه fallback. در ایران اکثریت پرداخت‌ها همین‌اند.

### `staff_payouts` — تسویهٔ صندلی (D06)
`salon_id` · `staff_id` · `period_start` · `period_end` ·
`service_revenue` · `product_revenue` · `commission_rate` · `commission_amount` ·
`booth_rent_amount` · `tips_amount` · `deductions_amount` · `deductions_note` ·
**`net_amount`** · `status` ENUM(`draft`,`finalized`,`paid`) · `finalized_at` · `paid_at`

`staff_payout_lines` — ریز هر قلم، تا فیش قابل دفاع باشد و بحث نشود.

---

## ۷. بقیهٔ جدول‌ها

| جدول | نکته |
|---|---|
| `working_hours` | `weekday` ۰=شنبه … ۶=جمعه — **تقویم ایرانی** |
| `time_offs` | مرخصی/تعطیلی؛ `staff_id` null = کل سالن |
| `holidays` | تعطیلات رسمی ایران، سراسری |
| `waitlist_entries` | `desired_from`/`desired_to` · `status` · `notified_at` |
| `products`, `product_sales` | فروش ژل و واکس + سهم آرایشگر |
| `loyalty_cards`, `loyalty_stamps` | کارت «۱۰ تا بزن، یکی مهمون ما» |
| `subscription_plans`, `customer_subscriptions` | اشتراک ماهانه (فاز ۳) |
| `sms_messages` | لاگ: `template`, `status`, `provider`, `cost`, `sent_at` |
| `sms_templates` | با پشتیبانی «الگو» (Pattern) — الزام اپراتورهای ایران |
| `sms_wallet_transactions` | شارژ و مصرف |
| `reviews` | از فاز ۲ جمع می‌شود، در فاز ۴ نمایش داده می‌شود |
| `audit_logs` | مخصوصاً ورود پشتیبانی به حساب سالن |
| `plans`, `platform_subscriptions`, `platform_invoices` | صورتحساب خودمان |

---

## ۸. ترتیب مهاجرت‌ها

```
0001 create_users_table
0002 create_cities_districts_holidays_tables
0003 create_salons_table
0004 create_salon_user_table
0005 create_staff_table
0006 create_services_and_staff_service_tables
0007 create_customers_table
0008 create_customer_preferences_and_photos_tables
0009 create_working_hours_and_time_offs_tables
0010 create_appointments_table              ← محوری
0011 create_appointment_items_table
0012 create_duration_stats_table            ← موتور ETA
0013 create_payments_table
0014 create_sms_tables
0015 create_audit_logs_table
--- فاز ۲ ---
0020 create_waitlist_entries_table
0021 create_staff_payouts_tables
0022 create_reviews_table
0023 add_trust_score_to_customers
0024 create_platform_billing_tables
--- فاز ۳ ---
0030 create_products_and_sales_tables
0031 create_loyalty_tables
0032 create_subscription_tables
0033 create_branches_table
```

---

## ۹. دو قاعده‌ای که هرگز شکسته نمی‌شوند

1. **هیچ کوئری‌ای بدون `salon_id` نوشته نمی‌شود.** اسکوپ سراسری این را خودکار می‌کند؛ هر
   `DB::table()` خام باید بازبینی دستی شود. ([۰۲ §دفاع چندلایه](02-multitenancy.md))
2. **زمان‌ها در دیتابیس UTC‌اند.** تبدیل به شمسی و `Asia/Tehran` فقط در لایهٔ نمایش. تخطی از این،
   در تغییر ساعت و در گزارش‌های مرزِ نیمه‌شب، باگ‌های غیرقابل‌ردیابی می‌سازد.
