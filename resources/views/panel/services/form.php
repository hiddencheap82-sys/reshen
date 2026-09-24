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
  <a href="<?= url('panel/services') ?>" class="w-11 h-11 -ms-2 grid place-items-center rounded-xl text-ink-400 tap">←</a>
  <h1 class="page-title"><?= $service ? 'ویرایش خدمت' : 'خدمت جدید' ?></h1>
</div>

<form method="post" action="<?= url($service ? 'panel/services/' . $service['id'] : 'panel/services') ?>" class="glass rounded-2xl p-5 space-y-4">
  <?= csrf_field() ?>
  <div>
    <label class="block text-sm text-ink-600 mb-1.5" for="name">نام خدمت</label>
    <input id="name" type="text" name="name" required value="<?= e($service['name'] ?? '') ?>" placeholder="اصلاح مو"
      class="field field-lg">
  </div>
  <div>
    <label for="svc-desc" class="block text-sm text-ink-600 mb-1.5">
      توضیح <span class="text-ink-400 font-normal">(اختیاری)</span>
    </label>
    <textarea name="description" id="svc-desc" rows="2" maxlength="300"
      placeholder="مثلاً: شست‌وشو، اصلاح با ماشین و قیچی، حالت‌دهی"
      class="field leading-relaxed"><?= e($service['description'] ?? '') ?></textarea>
    <p class="text-[12px] text-ink-400 mt-1.5">
      در «منوی خدمات» که مشتری می‌بیند نمایش داده می‌شود.
    </p>
  </div>

  <div class="grid grid-cols-2 gap-3">
    <div>
      <label class="block text-sm text-ink-600 mb-1.5" for="duration_minutes">مدت (دقیقه)</label>
      <input id="duration_minutes" inputmode="numeric" type="text" autocomplete="off" name="duration_minutes" value="<?= e(fa_num((int) ($service['duration_minutes'] ?? 30))) ?>"
        class="field field-lg">
    </div>
    <div>
      <label class="block text-sm text-ink-600 mb-1.5" for="price_toman">قیمت (تومان)</label>
      <input id="price_toman" inputmode="numeric" type="text" autocomplete="off" data-money name="price_toman" value="<?= e(fa_num((int) ($service ? App\Support\Money::fromRials((int)$service['price'])->toToman() : 0))) ?>"
        class="field field-lg">
    </div>
  </div>
  <button type="submit" class="btn-accent metal w-full">ذخیره</button>
</form>

<?php if ($service && !empty($staff)): ?>
<div class="glass rounded-2xl p-5 mt-4">
  <h2 class="card-title mb-1">مدت و قیمت اختصاصی هر آرایشگر</h2>
  <p class="text-xs text-ink-400 mb-4">اگر خالی بگذارید، عدد عمومی بالا استفاده می‌شود.</p>
  <div class="space-y-3">
  <?php foreach ($staff as $st): $ov = $overrideMap[(int)$st['id']] ?? null; ?>
    <form method="post" action="<?= url('panel/services/' . $service['id'] . '/override') ?>" class="flex items-center gap-2">
      <?= csrf_field() ?>
      <input type="hidden" name="staff_id" value="<?= (int)$st['id'] ?>">
      <span class="text-sm text-ink-700 w-24 shrink-0 truncate"><?= e($st['name']) ?></span>
      <input inputmode="numeric" type="text" autocomplete="off" name="duration_minutes" placeholder="دقیقه"
        aria-label="مدت این خدمت برای <?= e($st['name']) ?> (دقیقه)"
        value="<?= e($ov ? fa_num((int) $ov['duration_minutes']) : '') ?>"
        class="field w-20 text-center tabular-nums">
      <input inputmode="numeric" type="text" autocomplete="off" name="price_toman" placeholder="تومان"
        aria-label="قیمت این خدمت برای <?= e($st['name']) ?> (تومان)"
        value="<?= e($ov && $ov['price'] !== null ? fa_num((int) App\Support\Money::fromRials((int)$ov['price'])->toToman()) : '') ?>"
        class="field w-28 text-center tabular-nums">
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
