<?php
/**
 * نوار بخش‌های پنل پلتفرم.
 *
 * جدا از نوار پایینِ پنل سالن است و عمداً بالای صفحه می‌نشیند: این
 * پنل روی دسکتاپ استفاده می‌شود، نه وسط کار با قیچی. صاحب پلتفرم پشت
 * میز است.
 *
 * @var string $active کلید بخش فعال
 */
$items = [
    '' => ['نمای کلی', 'chart'],
    'salons' => ['سالن‌ها', 'users'],
    'plans' => ['پلن‌ها', 'tag'],
    'invoices' => ['صورتحساب', 'wallet'],
    'users' => ['کاربران', 'user'],
    'sms' => ['پیامک', 'message'],
    'activity' => ['فعالیت', 'clock'],
    'design' => ['دیزاین', 'sparkle'],
];
?>
<!--
  حاشیهٔ منفی باید با padding قالب جور باشد: <main> پنل ‎p-4‎ است
  (۱۶ پیکسل)، نه ‎p-5‎. با ‎-mx-5‎ نوار چهار پیکسل از هر طرف بیرون
  می‌زد و کل صفحه چهار پیکسل افقی اسکرول می‌خورد — کم، ولی روی موبایل
  حس می‌شود چون صفحه زیر انگشت تکان می‌خورد.
-->
<nav class="-mx-4 px-4 md:-mx-6 md:px-6 mb-5 overflow-x-auto no-scrollbar"
     aria-label="بخش‌های پنل پلتفرم">
  <div class="flex gap-1.5 w-max pb-1">
    <?php foreach ($items as $key => [$label, $iconName]): ?>
      <?php $on = ($active ?? '') === $key; ?>
      <a href="<?= e(url('platform' . ($key === '' ? '' : '/' . $key))) ?>"
         class="tap shrink-0 inline-flex items-center gap-1.5 h-11 px-3.5 rounded-xl
                text-[13px] font-semibold transition-colors
                focus-visible:outline-2 focus-visible:outline-accent
                <?= $on ? 'day-chip-on' : 'glass text-ink-700 hover:shadow-lift' ?>"
         <?= $on ? 'aria-current="page"' : '' ?>>
        <?= nav_icon($iconName, $on, 'w-4 h-4') ?>
        <?= e($label) ?>
      </a>
    <?php endforeach; ?>
  </div>
</nav>
