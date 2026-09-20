<?php
/** @var ?array $service
 * @var array $staff
 * @var array $overrides
 */
$staff ??= [];
$overrides ??= [];
$overrideMap = [];
foreach ($overrides as $o) { $overrideMap[(int)$o['staff_id']] = $o; }
?>
<div class="max-w-lg">
<div class="flex items-center gap-3 mb-5">
  <a href="<?= url('panel/services') ?>" class="text-ink-400">←</a>
  <h1 class="text-lg font-bold text-ink-800"><?= $service ? 'ویرایش خدمت' : 'خدمت جدید' ?></h1>
</div>

<form method="post" action="<?= url($service ? 'panel/services/' . $service['id'] : 'panel/services') ?>" class="glass rounded-2xl p-5 space-y-4">
  <?= csrf_field() ?>
  <div>
    <label class="block text-sm text-ink-600 mb-1.5">نام خدمت</label>
    <input type="text" name="name" required value="<?= e($service['name'] ?? '') ?>" placeholder="اصلاح مو"
      class="w-full rounded-xl border border-ink-200 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-accent">
  </div>
  <div class="grid grid-cols-2 gap-3">
    <div>
      <label class="block text-sm text-ink-600 mb-1.5">مدت (دقیقه)</label>
      <input type="number" name="duration_minutes" value="<?= e((string)($service['duration_minutes'] ?? 30)) ?>"
        class="w-full rounded-xl border border-ink-200 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-accent">
    </div>
    <div>
      <label class="block text-sm text-ink-600 mb-1.5">قیمت (تومان)</label>
      <input type="number" name="price_toman" value="<?= e((string)($service ? App\Support\Money::fromRials((int)$service['price'])->toToman() : 0)) ?>"
        class="w-full rounded-xl border border-ink-200 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-accent">
    </div>
  </div>
  <button type="submit" class="btn-ink w-full">ذخیره</button>
</form>

<?php if ($service && !empty($staff)): ?>
<div class="glass rounded-2xl p-5 mt-4">
  <h2 class="text-sm font-bold text-ink-700 mb-1">مدت و قیمت اختصاصی هر آرایشگر</h2>
  <p class="text-xs text-ink-400 mb-4">اگر خالی بگذارید، عدد عمومی بالا استفاده می‌شود.</p>
  <div class="space-y-3">
  <?php foreach ($staff as $st): $ov = $overrideMap[(int)$st['id']] ?? null; ?>
    <form method="post" action="<?= url('panel/services/' . $service['id'] . '/override') ?>" class="flex items-center gap-2">
      <?= csrf_field() ?>
      <input type="hidden" name="staff_id" value="<?= (int)$st['id'] ?>">
      <span class="text-sm text-ink-700 w-24 shrink-0 truncate"><?= e($st['name']) ?></span>
      <input type="number" name="duration_minutes" placeholder="دقیقه" value="<?= e($ov ? (string)$ov['duration_minutes'] : '') ?>"
        class="w-20 rounded-lg border border-ink-200 px-2 py-1.5 text-sm">
      <input type="number" name="price_toman" placeholder="تومان" value="<?= e($ov && $ov['price'] !== null ? (string) App\Support\Money::fromRials((int)$ov['price'])->toToman() : '') ?>"
        class="w-28 rounded-lg border border-ink-200 px-2 py-1.5 text-sm">
      <button type="submit" class="text-xs bg-ink-100 hover:bg-ink-200 rounded-lg px-3 py-1.5">ذخیره</button>
    </form>
  <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<?php if ($service): ?>
<form method="post" action="<?= url('panel/services/' . $service['id'] . '/toggle') ?>" class="mt-3">
  <?= csrf_field() ?>
  <button type="submit" class="w-full text-sm text-ink-400 hover:text-ink-600 py-2">
    <?= $service['is_active'] ? 'غیرفعال کردن' : 'فعال کردن' ?>
  </button>
</form>
<?php endif; ?>
</div>
