<?php /** @var array $services */ ?>
<div class="flex items-center justify-between mb-5">
  <h1 class="text-lg font-bold text-ink-800">خدمات</h1>
  <a href="<?= url('panel/services/create') ?>" class="bg-ink-900 hover:bg-ink-800 text-white text-sm font-bold rounded-xl px-4 py-2.5">+ افزودن</a>
</div>

<?php if (empty($services)): ?>
  <div class="bg-white rounded-2xl border border-dashed border-ink-200 p-10 text-center text-ink-400">
    هنوز خدمتی اضافه نکرده‌اید.
  </div>
<?php else: ?>
<div class="bg-white rounded-2xl border border-ink-100 divide-y divide-ink-100">
  <?php foreach ($services as $s): ?>
  <a href="<?= url('panel/services/' . $s['id'] . '/edit') ?>" class="flex items-center justify-between px-5 py-4 hover:bg-ink-50 <?= !$s['is_active'] ? 'opacity-50' : '' ?>">
    <div>
      <div class="font-bold text-ink-800"><?= e($s['name']) ?></div>
      <div class="text-xs text-ink-400 mt-0.5"><?= fa_num($s['duration_minutes']) ?> دقیقه</div>
    </div>
    <div class="text-sm font-bold text-gold-700"><?= toman((int)$s['price']) ?></div>
  </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>
