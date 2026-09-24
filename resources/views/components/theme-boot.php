<?php
/**
 * انتخاب روشن/تیره — پیش از رنگ‌آمیزی صفحه.
 *
 * چرا درون‌خطی و در <head>: اگر این اسکریپت بعد از رندر اجرا شود، کاربرِ
 * حالت تیره یک «پرشِ سفید» می‌بیند. باید قبل از اولین رنگ‌آمیزی، کلاس
 * روی <html> نشسته باشد.
 *
 * منطق: انتخاب ذخیره‌شدهٔ کاربر، وگرنه تنظیم سیستم‌عامل.
 */
?>
<script>
(function () {
  // بیرون از try تعریف می‌شود: اگر localStorage خطا بدهد (حالت ناشناس)
  // باز هم باید حالت سیستم اعمال شود و کلید بتواند کار کند.
  window.reshenApplyMode = function (dark) {
    document.documentElement.classList.toggle('dark', dark);
    document.documentElement.style.colorScheme = dark ? 'dark' : 'light';
    // نوار بالای مرورگر موبایل هم باید با صفحه یک‌رنگ باشد
    var m = document.getElementById('theme-color');
    // همان --hero: نفتی در روشن، نفتیِ عمیق در تیره
    if (m) { m.setAttribute('content', dark ? '#0A151E' : '#102431'); }
  };

  var saved = null;
  try {
    saved = localStorage.getItem('reshen-mode');      // روشن، تاریک، یا هیچ‌کدام (پیروی از سیستم)
  } catch (e) { /* حالت ناشناس: ذخیره‌سازی در دسترس نیست */ }

  window.reshenApplyMode(
    saved ? saved === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches
  );
})();
</script>
