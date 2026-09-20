<?php
/** @var string $phone
 * @var ?string $error
 * @var ?string $debugLine
 */
?>
<h2 class="text-base font-bold text-slate-800 mb-1">کد تأیید</h2>
<p class="text-sm text-slate-500 mb-5">کد ۵ رقمی ارسال‌شده به <span dir="ltr" class="font-mono"><?= e($phone) ?></span> را وارد کنید.</p>

<?php if ($error): ?>
<div class="bg-red-50 text-red-700 text-sm rounded-lg px-3 py-2 mb-4 border border-red-100"><?= e($error) ?></div>
<?php endif; ?>

<?php if ($debugLine): ?>
<div class="bg-amber-50 text-amber-800 text-xs rounded-lg px-3 py-2 mb-4 border border-amber-100 font-mono break-all" dir="ltr">
  DEV: <?= e($debugLine) ?>
</div>
<?php endif; ?>

<form method="post" action="<?= url('login/verify') ?>" class="space-y-4">
  <?= csrf_field() ?>
  <input type="text" name="code" inputmode="numeric" autofocus maxlength="5" placeholder="١٢٣٤٥"
    class="w-full rounded-xl border border-slate-200 px-4 py-3 text-center text-2xl tracking-[0.5em] focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent">
  <button type="submit" class="w-full bg-brand-600 hover:bg-brand-700 text-white font-bold rounded-xl py-3 transition">
    تأیید و ورود
  </button>
</form>
<div class="text-center mt-4">
  <a href="<?= url('login') ?>" class="text-xs text-slate-400 hover:text-slate-600">تغییر شماره</a>
</div>
