<?php /** @var array $salon @var array $services */ ?>
<h1 class="text-base font-bold text-slate-800 mb-1">رزرو نوبت</h1>
<p class="text-sm text-slate-500 mb-5">خدمت‌های مورد نظرتان را انتخاب کنید.</p>

<?php if ($error = flash('error')): ?>
<div class="bg-red-50 text-red-700 text-sm rounded-lg px-3 py-2 mb-4 border border-red-100"><?= e($error) ?></div>
<?php endif; ?>

<?php if (empty($services)): ?>
  <p class="text-sm text-slate-400">در حال حاضر خدمتی برای رزرو تعریف نشده است.</p>
<?php else: ?>
<form method="post" action="<?= url('s/' . $salon['slug']) ?>" class="space-y-2">
  <?= csrf_field() ?>
  <?php foreach ($services as $s): ?>
  <label class="flex items-center justify-between border border-slate-200 rounded-xl px-4 py-3 cursor-pointer has-[:checked]:border-brand-400 has-[:checked]:bg-brand-50">
    <span class="flex items-center gap-2.5">
      <input type="checkbox" name="service_ids[]" value="<?= (int)$s['id'] ?>" class="w-4 h-4 accent-brand-600">
      <span class="text-sm font-medium text-slate-800"><?= e($s['name']) ?></span>
    </span>
    <span class="text-xs text-slate-400"><?= fa_num($s['duration_minutes']) ?> دقیقه · <?= toman((int)$s['price']) ?></span>
  </label>
  <?php endforeach; ?>
  <button type="submit" class="w-full bg-brand-600 hover:bg-brand-700 text-white font-bold rounded-xl py-3 mt-4">ادامه</button>
</form>
<?php endif; ?>
