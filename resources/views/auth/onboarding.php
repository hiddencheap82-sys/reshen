<?php /** @var ?string $error */ ?>
<h2 class="text-[15px] font-extrabold text-ink-900 mb-1">اولین سالن‌تان را بسازید</h2>
<p class="text-sm text-ink-500 mb-5">کمتر از ۲ دقیقه. جزئیات را بعداً هم می‌توانید کامل کنید.</p>

<?php if ($error): ?>
<div class="bg-bad-soft text-bad text-sm rounded-lg px-3 py-2 mb-4 border border-red-100"><?= e($error) ?></div>
<?php endif; ?>

<form method="post" action="<?= url('onboarding') ?>" class="space-y-4">
  <?= csrf_field() ?>
  <div>
    <label class="block text-sm text-ink-600 mb-1.5" for="name">نام سالن</label>
    <input id="name" type="text" name="name" required autofocus placeholder="آرایشگاه شهاب"
      class="field field-lg">
  </div>
  <div class="grid grid-cols-2 gap-3">
    <div>
      <label class="block text-sm text-ink-600 mb-1.5" for="city">شهر</label>
      <input id="city" type="text" name="city" placeholder="شیراز"
        class="field field-lg">
    </div>
    <div>
      <label class="block text-sm text-ink-600 mb-1.5" for="seats">تعداد صندلی</label>
      <input id="seats" inputmode="numeric" type="text" autocomplete="off" name="seats" value="۱"
        class="field field-lg">
    </div>
  </div>
  <div>
    <label class="block text-sm text-ink-600 mb-1.5" for="address">آدرس (اختیاری)</label>
    <input id="address" type="text" name="address" placeholder="خیابان..."
      class="field field-lg">
  </div>
  <button type="submit" class="btn-accent metal w-full">
    ساخت سالن و شروع
  </button>
</form>
