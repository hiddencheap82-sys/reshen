<?php /** @var array $salon @var array $staff */ ?>
<h1 class="text-base font-bold text-ink-800 mb-1">انتخاب آرایشگر</h1>
<p class="text-sm text-ink-500 mb-5">می‌توانید انتخاب نکنید تا زودترین وقت آزاد پیشنهاد شود.</p>

<form method="post" action="<?= url('s/' . $salon['slug'] . '/staff') ?>" class="space-y-2">
  <?= csrf_field() ?>
  <label class="flex items-center gap-2.5 border border-ink-200 rounded-xl px-4 py-3 cursor-pointer has-[:checked]:border-gold-600 has-[:checked]:bg-gold-50">
    <input type="radio" name="staff_id" value="" checked class="w-4 h-4 accent-brand-600">
    <span class="text-sm font-medium text-ink-800">فرقی نمی‌کند</span>
  </label>
  <?php foreach ($staff as $st): ?>
  <label class="flex items-center gap-2.5 border border-ink-200 rounded-xl px-4 py-3 cursor-pointer has-[:checked]:border-gold-600 has-[:checked]:bg-gold-50">
    <input type="radio" name="staff_id" value="<?= (int)$st['id'] ?>" class="w-4 h-4 accent-brand-600">
    <span class="w-7 h-7 rounded-full flex items-center justify-center text-white text-xs font-bold" style="background:<?= e($st['color']) ?>"><?= e(mb_substr($st['name'],0,1)) ?></span>
    <span class="text-sm font-medium text-ink-800"><?= e($st['name']) ?></span>
  </label>
  <?php endforeach; ?>
  <button type="submit" class="btn-ink w-full mt-4">ادامه</button>
</form>
