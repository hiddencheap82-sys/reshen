<?php /** @var array $salon @var string $phone @var ?string $debugLine */ ?>
<h1 class="text-[15px] font-extrabold text-ink-900 mb-1">کد تأیید</h1>
<p class="text-sm text-ink-500 mb-5">کد ۵ رقمی ارسال‌شده به <span dir="ltr" class="tabular-nums ltr"><?= e($phone) ?></span> را وارد کنید.</p>

<?php if ($debugLine): ?>
<div class="bg-warn-soft text-amber-800 text-xs rounded-lg px-3 py-2 mb-4 border border-amber-100 code break-all" dir="ltr">DEV: <?= e($debugLine) ?></div>
<?php endif; ?>

<form method="post" action="<?= url('s/' . $salon['slug'] . '/verify') ?>" class="space-y-4">
  <?= csrf_field() ?>
  <label for="booking-code" class="sr-only">کد پنج‌رقمی که پیامک شد</label>
  <input type="text" id="booking-code" name="code" inputmode="numeric" autocomplete="one-time-code"
    autofocus maxlength="5" placeholder="١٢٣٤٥"
    class="field field-lg text-center text-2xl tracking-[0.5em]">
  <button type="submit" class="btn-accent metal w-full">تأیید نهایی نوبت</button>
</form>
