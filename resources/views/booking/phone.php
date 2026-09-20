<?php /** @var array $salon */ ?>
<h1 class="text-base font-bold text-slate-800 mb-1">شمارهٔ موبایل</h1>
<p class="text-sm text-slate-500 mb-5">برای تأیید نهایی نوبت، شماره‌تان را وارد کنید. نیاز به نصب یا رمز نیست.</p>

<?php if ($error = flash('error')): ?>
<div class="bg-red-50 text-red-700 text-sm rounded-lg px-3 py-2 mb-4 border border-red-100"><?= e($error) ?></div>
<?php endif; ?>

<form method="post" action="<?= url('s/' . $salon['slug'] . '/phone') ?>" class="space-y-4">
  <?= csrf_field() ?>
  <div>
    <label class="block text-sm text-slate-600 mb-1.5">نام (اختیاری)</label>
    <input type="text" name="name" class="w-full rounded-xl border border-slate-200 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-brand-500">
  </div>
  <div>
    <label class="block text-sm text-slate-600 mb-1.5">شمارهٔ موبایل</label>
    <input type="tel" name="phone" required dir="ltr" placeholder="۰۹۱۲۳۴۵۶۷۸۹" autofocus
      class="w-full rounded-xl border border-slate-200 px-4 py-3 text-left text-lg tracking-wider focus:outline-none focus:ring-2 focus:ring-brand-500">
  </div>
  <button type="submit" class="w-full bg-brand-600 hover:bg-brand-700 text-white font-bold rounded-xl py-3">ارسال کد تأیید</button>
</form>
