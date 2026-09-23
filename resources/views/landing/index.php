<?php
/**
 * دروازهٔ دسترسی.
 *
 * ترتیب کارت‌ها بر اساس این است که *چند نفر* هر کدام را می‌خواهند، نه
 * اینکه کدام مهم‌تر است. مشتری‌ها از همه بیشترند، پس اولند — حتی
 * اگر صاحب سالن پول می‌دهد.
 *
 * @var bool $isLoggedIn
 * @var bool $isPlatformAdmin
 * @var array $salons عضویت‌های کاربر واردشده
 * @var string|null $customerPhone
 * @var int $salonCount
 */
?>

<?php if ($isLoggedIn && $salons !== []): ?>
  <!--
    کاربر واردشده. مسیر خودش بالای همه می‌آید تا یک کلیک کمتر بخورد —
    کسی که هر روز صبح پنلش را باز می‌کند نباید هر بار از میان گزینه‌ها
    انتخاب کند.
  -->
  <section class="mb-5">
    <h2 class="text-[12px] font-bold text-ink-400 mb-2">ادامهٔ کار</h2>
    <a href="<?= e(url('panel')) ?>"
       class="tap glass rounded-2xl p-4 flex items-center gap-3 hover:shadow-lift transition-shadow">
      <span class="w-11 h-11 shrink-0 rounded-xl grid place-items-center"
            style="background:var(--accent-soft);color:var(--accent)" aria-hidden="true">
        <?= icon('queue', 'w-5 h-5') ?>
      </span>
      <span class="flex-1 min-w-0">
        <span class="block text-[14px] font-bold text-ink-900">پنل آرایشگاه</span>
        <span class="block text-[12px] text-ink-400 truncate">
          <?= e($salons[0]['salon_name'] ?? 'سالن شما') ?>
          <?php if (count($salons) > 1): ?>
            و <?= e(fa_num(count($salons) - 1)) ?> سالن دیگر
          <?php endif; ?>
        </span>
      </span>
      <?= icon('chevron-end', 'w-4 h-4 text-ink-400') ?>
    </a>
  </section>
<?php endif; ?>

<?php if ($isPlatformAdmin): ?>
  <section class="mb-5">
    <a href="<?= e(url('platform')) ?>"
       class="tap glass rounded-2xl p-4 flex items-center gap-3 hover:shadow-lift transition-shadow">
      <span class="w-11 h-11 shrink-0 rounded-xl grid place-items-center text-warn"
            style="background:var(--accent-soft)" aria-hidden="true">
        <?= icon('shield', 'w-5 h-5') ?>
      </span>
      <span class="flex-1 min-w-0">
        <span class="block text-[14px] font-bold text-ink-900">پنل پلتفرم</span>
        <span class="block text-[12px] text-ink-400">همهٔ سالن‌ها، پلن‌ها و صورتحساب‌ها</span>
      </span>
      <?= icon('chevron-end', 'w-4 h-4 text-ink-400') ?>
    </a>
  </section>
<?php endif; ?>

<h1 class="text-[17px] font-extrabold text-ink-900 mb-1">کجا می‌خواهی بروی؟</h1>
<p class="text-[13px] text-ink-500 mb-5 leading-relaxed">
  <?php if ($salonCount === 0): ?>
    هنوز هیچ آرایشگاهی روی این سامانه ثبت نشده.
  <?php else: ?>
    رشن نوبت‌دهی و صف زندهٔ آرایشگاه است.
  <?php endif; ?>
</p>

<div class="space-y-2.5">

  <!-- مشتری -->
  <a href="<?= e(url($customerPhone !== null ? 'me' : 'me/login')) ?>"
     class="tap glass rounded-2xl p-4 flex items-center gap-3 hover:shadow-lift transition-shadow">
    <span class="w-11 h-11 shrink-0 rounded-xl grid place-items-center"
          style="background:var(--fill-secondary)" aria-hidden="true">
      <?= icon('calendar', 'w-5 h-5 text-ink-600') ?>
    </span>
    <span class="flex-1 min-w-0">
      <span class="block text-[14px] font-bold text-ink-900">نوبت‌های من</span>
      <span class="block text-[12px] text-ink-400">
        <?= $customerPhone !== null
            ? 'نوبت‌هایت را ببین یا لغو کن'
            : 'با شمارهٔ موبایلت وارد شو' ?>
      </span>
    </span>
    <?= icon('chevron-end', 'w-4 h-4 text-ink-400') ?>
  </a>

  <!-- صاحب سالن -->
  <?php if (!$isLoggedIn): ?>
    <a href="<?= e(url('login')) ?>"
       class="tap glass rounded-2xl p-4 flex items-center gap-3 hover:shadow-lift transition-shadow">
      <span class="w-11 h-11 shrink-0 rounded-xl grid place-items-center"
            style="background:var(--fill-secondary)" aria-hidden="true">
        <?= icon('scissors', 'w-5 h-5 text-ink-600') ?>
      </span>
      <span class="flex-1 min-w-0">
        <span class="block text-[14px] font-bold text-ink-900">ورود آرایشگاه</span>
        <span class="block text-[12px] text-ink-400">
          <?= $salonCount === 0 ? 'اولین سالن را همین‌جا بساز' : 'صف، نوبت‌ها و مشتری‌ها' ?>
        </span>
      </span>
      <?= icon('chevron-end', 'w-4 h-4 text-ink-400') ?>
    </a>
  <?php endif; ?>

</div>

<!--
  لینک سالن را چطور پیدا می‌کنند: هر سالن نشانی خودش را دارد و ما
  فهرستشان را اینجا نمی‌آوریم — این صفحه دروازه است، نه مارکت‌پلیس.
  ولی سؤال «لینکم را گم کردم» واقعی است و باید جوابی داشته باشد.
-->
<?php if ($salonCount > 0): ?>
  <p class="text-[12px] text-ink-400 mt-6 leading-relaxed">
    برای گرفتن نوبت، از لینک یا QR خودِ آرایشگاه وارد شوید. اگر قبلاً نوبت
    گرفته‌اید، «نوبت‌های من» همهٔ آرایشگاه‌هایتان را یک‌جا نشان می‌دهد.
  </p>
<?php endif; ?>

<?php if ($isLoggedIn): ?>
  <div class="mt-6 pt-4" style="border-top:1px solid var(--line)">
    <a href="<?= e(url('logout')) ?>" class="tap inline-flex items-center h-11 text-[13px] text-ink-500">
      خروج از حساب
    </a>
  </div>
<?php endif; ?>
