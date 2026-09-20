<?php
/** @var array $salon @var array $days @var string $selectedDate @var array $slots */
?>
<h1 class="text-base font-bold text-slate-800 mb-1">انتخاب زمان</h1>
<p class="text-sm text-slate-500 mb-4">روز و ساعت موردنظرتان را انتخاب کنید.</p>

<div class="flex gap-2 overflow-x-auto pb-2 mb-4 -mx-1 px-1">
  <?php foreach ($days as $d): $active = $d['value'] === $selectedDate; ?>
  <a href="<?= url('s/' . $salon['slug'] . '/slots?date=' . $d['value']) ?>"
     class="shrink-0 text-center rounded-xl px-3 py-2 text-xs border <?= $active ? 'bg-brand-600 border-brand-600 text-white' : 'border-slate-200 text-slate-600' ?>">
    <?= e($d['label']) ?>
  </a>
  <?php endforeach; ?>
</div>

<?php if (empty($slots)): ?>
  <div class="text-center text-sm text-slate-400 py-10">برای این روز زمانی آزاد نیست.</div>
<?php else: ?>
<form method="post" action="<?= url('s/' . $salon['slug'] . '/slots') ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="date" value="<?= e($selectedDate) ?>">
  <div class="grid grid-cols-3 gap-2 mb-4">
    <?php foreach ($slots as $t): ?>
    <label class="text-center border border-slate-200 rounded-xl py-2.5 text-sm cursor-pointer has-[:checked]:bg-brand-600 has-[:checked]:text-white has-[:checked]:border-brand-600">
      <input type="radio" name="time" value="<?= e($t) ?>" class="hidden" required><?= fa_num($t) ?>
    </label>
    <?php endforeach; ?>
  </div>
  <button type="submit" class="w-full bg-brand-600 hover:bg-brand-700 text-white font-bold rounded-xl py-3">ادامه</button>
</form>
<?php endif; ?>
