<?php
/**
 * فهرست سالن‌ها.
 *
 * @var array $salons
 * @var array $plans
 * @var string $q
 * @var string $status
 */
$active = 'salons';
include __DIR__ . '/_nav.php';
?>

<div class="flex items-start justify-between gap-3 mb-4">
  <h1 class="page-title">سالن‌ها</h1>
  <a href="<?= e(url('platform/salons/create')) ?>"
     class="btn-accent metal h-11 px-4 text-[13px] shrink-0">
    <?= icon('plus', 'w-4 h-4') ?>
    سالن تازه
  </a>
</div>

<!--
  شبکه، نه flex: ‎.field‎ پهنای ۱۰۰٪ دارد و در ردیفِ flex، فهرستِ «وضعیت»
  همهٔ ردیف را می‌گرفت و جعبهٔ جستجو روی گوشی ۲۶ پیکسل می‌ماند.
-->
<form method="get" action="<?= e(url('platform/salons')) ?>" class="grid grid-cols-[minmax(0,1fr)_auto_auto] gap-2 mb-4">
  <label for="salon-q" class="sr-only">جستجوی سالن</label>
  <input type="search" id="salon-q" name="q" value="<?= e($q) ?>"
         placeholder="نام، نشانی یا شهر"
         class="field flex-1 min-w-0">
  <label for="salon-status" class="sr-only">وضعیت</label>
  <select id="salon-status" name="status"
          class="field">
    <option value="">همه</option>
    <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>فعال</option>
    <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>غیرفعال</option>
  </select>
  <button type="submit" class="btn-ink h-11 px-4 text-[13px]">جستجو</button>
</form>

<?php if ($salons === []): ?>
  <div class="glass rounded-2xl py-12 text-center">
    <p class="text-sm text-ink-500">سالنی با این مشخصات پیدا نشد.</p>
  </div>
<?php else: ?>
  <div class="glass rounded-2xl divide-y" style="border-color:var(--line)">
    <?php foreach ($salons as $s): ?>
      <a href="<?= e(url('platform/' . $s['id'])) ?>"
         class="tap flex items-center gap-3 px-4 py-3.5 hover:bg-ink-50">
        <span class="flex-1 min-w-0">
          <span class="flex items-center gap-2">
            <span class="font-bold text-ink-900 truncate"><?= e($s['name']) ?></span>
            <?php if (!$s['is_active']): ?>
              <span class="text-[12px] rounded-full px-2 py-0.5 bg-ink-100 text-ink-500 shrink-0">غیرفعال</span>
            <?php endif; ?>
          </span>
          <span class="block text-[12px] text-ink-400 mt-0.5">
            <?= e($s['city'] ?? '—') ?> ·
            <?= e(fa_num((int) $s['staff_count'])) ?> آرایشگر ·
            <?= e(fa_num((int) $s['customer_count'])) ?> مشتری
          </span>
          <span class="block text-[12px] text-ink-400 mt-0.5">
            <?php if ($s['last_booking_at'] === null): ?>
              <span class="text-warn">هنوز نوبتی ثبت نکرده</span>
            <?php else: ?>
              <?= e(fa_num((int) $s['bookings_30d'])) ?> نوبت در ۳۰ روز ·
              آخرین: <?= e(jdate($s['last_booking_at'], 'Y/m/d')) ?>
            <?php endif; ?>
          </span>
        </span>
        <span class="text-left shrink-0">
          <span class="text-[12px] px-2 py-0.5 rounded-full"
                style="background:var(--accent-soft);color:var(--accent)">
            <?= e($plans[$s['plan_code']] ?? $s['plan_code']) ?>
          </span>
          <span class="block text-[12px] text-ink-400 mt-1 tabular-nums">
            <?= e(fa_num((int) $s['seats'])) ?> صندلی
          </span>
        </span>
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
