<?php
/**
 * پیام‌های لحظه‌ای — یک جا برای کل برنامه.
 *
 * پیش از این سه شکل جدا داشت: نوار تخت و بی‌آیکون در پنل، کارت
 * آیکون‌دار در صفحهٔ مشتری، و همان کارت که در پنج ویوی رزرو کپی شده
 * بود. دو لایه — auth و plain — اصلاً چیزی نشان نمی‌دادند، و پیام
 * «دسترسی به این سالن ندارید» بی‌صدا گم می‌شد: کاربر روی سالن کلیک
 * می‌کرد، به همان صفحه برمی‌گشت، و هیچ توضیحی نمی‌دید.
 *
 * دو حالت دارد:
 *   بدون جاوااسکریپت — کارتی در جریان صفحه، همان‌جا که بود.
 *   با جاوااسکریپت — نوار شناور پایین صفحه، نزدیک انگشت، که موفقیت
 *   را خودش جمع می‌کند و خطا را تا زدن ضربدر نگه می‌دارد.
 *
 * چرا پایین: بعد از زدن یک دکمه، چشم و انگشت پایین صفحه‌اند. پیامی
 * که بالای صفحه بنشیند، روی موبایل معمولاً دیده نمی‌شود — مخصوصاً
 * وقتی صفحه بلند است و کاربر پایین مانده.
 */
$flashSuccess = flash('success');
$flashError = flash('error');

if ($flashSuccess === null && $flashError === null) {
    return;
}

/*
 * نام کامل کلاس، نه «flash-» به‌اضافهٔ متغیر.
 *
 * تیلویند فقط کلاس‌هایی را می‌سازد که در فایل‌ها *دیده* باشد. با
 * ‎class="flash flash-<?= $kind ?>"‎ پویشگر رشتهٔ «flash-success» را
 * هیچ‌جا نمی‌بیند و قاعده‌اش را از خروجی حذف می‌کند — کارت پیام
 * بی‌رنگ و بی‌پس‌زمینه رندر می‌شود، بدون هیچ خطایی.
 */
$flashItems = [];
if ($flashError !== null) {
    // خطا اول: اگر هر دو باشند، آن که کار را متوقف کرده مهم‌تر است.
    $flashItems[] = ['error', 'flash-error', (string) $flashError, 'alert', 'alert', 'assertive'];
}
if ($flashSuccess !== null) {
    $flashItems[] = ['success', 'flash-success', (string) $flashSuccess, 'check', 'status', 'polite'];
}
?>
<div class="flash-stack" id="flash-stack">
  <?php foreach ($flashItems as [$kind, $toneClass, $message, $iconName, $role, $live]): ?>
    <div class="flash <?= $toneClass ?>" role="<?= $role ?>" aria-live="<?= $live ?>"
         data-flash="<?= $kind ?>">
      <span class="shrink-0 mt-0.5" aria-hidden="true"><?= icon($iconName, 'w-4 h-4') ?></span>
      <span class="flex-1 min-w-0 text-[13px] leading-relaxed"><?= e($message) ?></span>
      <button type="button" class="flash-x" aria-label="بستن پیام" hidden>
        <?= icon('close', 'w-4 h-4') ?>
      </button>
    </div>
  <?php endforeach; ?>
</div>

<script>
(function () {
  var stack = document.getElementById('flash-stack');
  if (!stack) { return; }

  // شناور فقط وقتی اسکریپت هست. بدون این، کاربرِ بی‌جاوااسکریپت یک
  // نوار همیشگی می‌گرفت که چیزی را می‌پوشاند و بسته هم نمی‌شد.
  stack.classList.add('flash-float');

  Array.prototype.forEach.call(stack.querySelectorAll('.flash'), function (el) {
    var close = el.querySelector('.flash-x');
    if (close) {
      close.hidden = false;
      close.addEventListener('click', function () { dismiss(el); });
    }

    // موفقیت خودش می‌رود، خطا نه: خطا معمولاً کاری برای انجام دادن
    // دارد و اگر پیش از خواندن ناپدید شود، کاربر نمی‌داند چه شد.
    if (el.getAttribute('data-flash') === 'success') {
      setTimeout(function () { dismiss(el); }, 5000);
    }
  });

  function dismiss(el) {
    el.classList.add('flash-out');
    setTimeout(function () {
      el.remove();
      if (!stack.querySelector('.flash')) { stack.remove(); }
    }, 220);
  }
})();
</script>
