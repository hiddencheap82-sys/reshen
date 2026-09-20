<?php /** @var ?string $error */ ?>
<h2 class="text-base font-bold text-ink-800 mb-1">اولین سالن‌تان را بسازید</h2>
<p class="text-sm text-ink-500 mb-5">کمتر از ۲ دقیقه. جزئیات را بعداً هم می‌توانید کامل کنید.</p>

<?php if ($error): ?>
<div class="bg-red-50 text-red-700 text-sm rounded-lg px-3 py-2 mb-4 border border-red-100"><?= e($error) ?></div>
<?php endif; ?>

<form method="post" action="<?= url('onboarding') ?>" class="space-y-4">
  <?= csrf_field() ?>
  <div>
    <label class="block text-sm text-ink-600 mb-1.5">نام سالن</label>
    <input type="text" name="name" required autofocus placeholder="آرایشگاه شهاب"
      class="w-full rounded-xl border border-ink-200 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent">
  </div>
  <div class="grid grid-cols-2 gap-3">
    <div>
      <label class="block text-sm text-ink-600 mb-1.5">شهر</label>
      <input type="text" name="city" placeholder="شیراز"
        class="w-full rounded-xl border border-ink-200 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent">
    </div>
    <div>
      <label class="block text-sm text-ink-600 mb-1.5">تعداد صندلی</label>
      <input type="number" name="seats" value="1" min="1" max="20"
        class="w-full rounded-xl border border-ink-200 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent">
    </div>
  </div>
  <div>
    <label class="block text-sm text-ink-600 mb-1.5">آدرس (اختیاری)</label>
    <input type="text" name="address" placeholder="خیابان..."
      class="w-full rounded-xl border border-ink-200 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent">
  </div>
  <button type="submit" class="w-full bg-ink-900 hover:bg-ink-800 text-white font-bold rounded-xl py-3 transition">
    ساخت سالن و شروع
  </button>
</form>
