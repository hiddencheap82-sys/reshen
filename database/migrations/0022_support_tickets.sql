-- پشتیبانی: راهی که صاحب سالن می‌تواند حرف بزند.
--
-- تا حالا نداشت. اگر چیزی خراب می‌شد — پیامک نمی‌رفت، سانس اشتباه
-- نشان داده می‌شد — صاحب سالن هیچ راهی نداشت جز زنگ زدن به شماره‌ای
-- که شاید داشت شاید نداشت. و ما هیچ راهی نداشتیم بفهمیم چند نفر
-- دیگر همان مشکل را دارند.
--
-- چرا داخل خود برنامه و نه ایمیل یا تلگرام:
--
-- ۱. **زمینه همراهش می‌آید.** تیکت به `salon_id` بسته است، پس پشتیبان
--    بدون پرسیدن می‌داند طرف کیست، چه پلنی دارد و سالنش چه ایرادی
--    دارد. در ایمیل، اول سه پیام می‌رود و می‌آید تا معلوم شود کدام
--    سالن.
--
-- ۲. **صاحب سالن جای دیگری نمی‌رود.** همان پنلی که هر روز بازش
--    می‌کند.
--
-- ۳. **گم نمی‌شود.** تیکتِ بی‌جواب روی صفحهٔ نخستِ پنل پلتفرم شمرده
--    می‌شود.
--
-- عمداً ساده است: نه اولویت، نه دسته‌بندی، نه تخصیص به اپراتور. یک
-- عنوان، یک رشته پیام، و سه وضعیت. پشتیبانیِ محصولی که ده‌ها سالن
-- دارد، نه صدها.

CREATE TABLE IF NOT EXISTS `support_tickets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  -- تیکت همیشه از یک سالن می‌آید. مدیر پلتفرمی که سالن ندارد تیکت
  -- نمی‌سازد؛ او جواب می‌دهد.
  `salon_id` bigint(20) unsigned NOT NULL,
  `opened_by_user_id` bigint(20) unsigned DEFAULT NULL,
  `subject` varchar(150) NOT NULL,
  -- open      = تازه، هنوز جواب نگرفته
  -- answered  = پشتیبان جواب داده، منتظر سالن
  -- closed    = تمام
  `status` enum('open','answered','closed') NOT NULL DEFAULT 'open',
  -- برای شمردنِ «چند تیکت منتظر ماست» بدون join به پیام‌ها.
  `last_message_at` timestamp NULL DEFAULT NULL,
  `closed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `support_tickets_salon_idx` (`salon_id`, `status`),
  KEY `support_tickets_status_idx` (`status`, `last_message_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `support_messages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ticket_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  -- از کدام طرف: سالن یا پشتیبانی. نقشِ کاربر را ذخیره نمی‌کنیم چون
  -- ممکن است بعداً عوض شود و آن‌وقت گفتگوی قدیمی معنی‌اش را از دست
  -- می‌دهد.
  `side` enum('salon','platform') NOT NULL,
  `body` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `support_messages_ticket_idx` (`ticket_id`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
