-- رمز عبور برای ورود، کنار کد پیامکی.
--
-- تا حالا ورود فقط با کد پیامکی بود و روی کاغذ بهتر هم هست: چیزی برای
-- فراموش کردن یا لو رفتن ندارد. ولی روی هاست تازه دو مشکل داشت:
--
-- ۱. پیامک هنوز تنظیم نشده. SMS_DRIVER پیش‌فرض `log` است، یعنی کد
--    فقط در storage/logs/sms.log نوشته می‌شود. کاربرِ cPanel باید با
--    File Manager دنبال فایل بگردد تا بتواند وارد *برنامهٔ خودش* شود.
--
-- ۲. مدیر کل شدن فقط با SSH ممکن بود
--    (`php tools/make_platform_admin.php`). روی هاست اشتراکی ایرانی
--    SSH معمولاً نیست، پس پنل مدیریت کل عملاً دست‌نیافتنی بود.
--
-- حالا نصاب همان‌جا یک مدیر کل با رمز می‌سازد و کار تمام است.
--
-- ستون nullable است و همین‌طور هم می‌ماند: مشتری‌ها هیچ‌وقت رمز
-- نمی‌گذارند و نباید مجبور شوند. رمز چیزی است که *کارکنان* می‌گذارند.

ALTER TABLE `users`
  ADD COLUMN `password_hash` varchar(255) DEFAULT NULL
      COMMENT 'password_hash(PASSWORD_DEFAULT) — null یعنی این کاربر فقط با کد پیامکی وارد می‌شود'
      AFTER `name`,
  ADD COLUMN `password_updated_at` timestamp NULL DEFAULT NULL
      AFTER `password_hash`;

-- تلاش‌های ناموفق ورود با رمز.
--
-- جدا از otp_codes است چون سؤالِ متفاوتی می‌پرسد: آنجا «این کد درست
-- است؟»، اینجا «این شماره یا این IP دارد رمز حدس می‌زند؟».
--
-- بدون این، رمزِ چهار رقمیِ یک آرایشگر در چند دقیقه شکسته می‌شود.
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `phone` varchar(15) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `succeeded` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_attempt_phone_time` (`phone`,`created_at`),
  KEY `idx_attempt_ip_time` (`ip_address`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
