<?php /** @var array $services */ ?>
<div class="flex items-center justify-between mb-5">
  <h1 class="page-title">خدمات</h1>
  <a href="<?= url('panel/services/create') ?>" class="btn-accent metal h-11 text-sm px-4">+ افزودن</a>
</div>

<?php if (empty($services)): ?>
  <div class="glass rounded-2xl p-10 text-center text-ink-400">
    هنوز خدمتی اضافه نکرده‌اید.
  </div>
<?php else: ?>
<div class="glass rounded-2xl divide-y divide-ink-100">
  <?php foreach ($services as $s): ?>
  <a href="<?= url('panel/services/' . $s['id'] . '/edit') ?>" class="flex items-center justify-between px-5 py-4 hover:bg-ink-50 <?= !$s['is_active'] ? 'opacity-50' : '' ?>">
    <div>
      <div class="font-bold text-ink-800"><?= e($s['name']) ?></div>
      <div class="text-xs text-ink-400 mt-0.5"><?= fa_num($s['duration_minutes']) ?> دقیقه</div>
    </div>
    <div class="text-sm font-bold text-accent"><?= toman((int)$s['price']) ?></div>
  </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>
