<?php
/**
 * منوی خدمات — چیزی که مشتری پیش از تصمیم می‌خواند.
 *
 * @var array $salon
 * @var array $services
 * @var array $liveStatus
 */

$total = count($services);
$cheapest = $services === [] ? 0 : min(array_map(static fn ($s) => (int) $s['price'], $services));
$shortest = $services === [] ? 0 : min(array_map(static fn ($s) => (int) $s['duration_minutes'], $services));
?>

<div class="rise mb-5">
  <h1 class="page-title mb-1">خدمات و قیمت‌ها</h1>
  <p class="text-[12px] text-ink-500 leading-relaxed">
    <?php if ($total > 0): ?>
      <?= e(fa_num($total)) ?> خدمت، از <?= e(toman($cheapest)) ?> و از <?= e(fa_num($shortest)) ?> دقیقه.
    <?php else: ?>
      هنوز خدمتی ثبت نشده.
    <?php endif; ?>
  </p>
</div>

<?php if ($services === []): ?>
  <div class="glass rounded-2xl py-12 px-5 text-center rise rise-1">
    <?= icon('scissors', 'w-10 h-10 mx-auto text-ink-300 mb-3') ?>
    <p class="text-sm font-semibold text-ink-600">هنوز خدمتی تعریف نشده</p>
    <?php if (!empty($salon['phone'])): ?>
      <a href="tel:<?= e($salon['phone']) ?>" class="btn-ink mt-4 inline-flex">تماس با سالن</a>
    <?php endif; ?>
  </div>

<?php else: ?>
  <ul class="space-y-2.5">
    <?php foreach ($services as $i => $s): ?>
      <li class="glass rounded-2xl px-4 py-3.5 rise rise-<?= min($i + 1, 5) ?>">
        <div class="flex items-start gap-3">
          <span class="w-9 h-9 shrink-0 rounded-xl grid place-items-center"
                style="background:var(--accent-soft);color:var(--accent)" aria-hidden="true">
            <?= icon('scissors', 'w-4 h-4') ?>
          </span>

          <div class="flex-1 min-w-0">
            <h2 class="card-title"><?= e($s['name']) ?></h2>

            <?php if (!empty($s['description'])): ?>
              <p class="text-[12px] text-ink-500 mt-1 leading-relaxed"><?= e($s['description']) ?></p>
            <?php endif; ?>

            <p class="flex items-center gap-1.5 text-[12px] text-ink-400 mt-1.5">
              <?= icon('clock', 'w-3.5 h-3.5 shrink-0') ?>
              <span class="tabular-nums"><?= e(fa_num((int) $s['duration_minutes'])) ?> دقیقه</span>
            </p>
          </div>

          <span class="text-[13px] font-extrabold text-ink-900 tabular-nums shrink-0 mt-0.5">
            <?= e(toman((int) $s['price'])) ?>
          </span>
        </div>
      </li>
    <?php endforeach; ?>
  </ul>

  <div class="sticky bottom-3 mt-5">
    <a href="<?= e(url('s/' . $salon['slug'])) ?>" class="btn-accent metal w-full shadow-deep">
      <?= icon('calendar', 'w-4 h-4') ?>
      رزرو نوبت
    </a>
  </div>

  <p class="text-[12px] text-ink-400 text-center mt-3 leading-relaxed">
    قیمت‌ها ممکن است بسته به نوع مو و زمان تغییر کند.
    <?php if (!empty($salon['phone'])): ?>
      برای اطمینان <a href="tel:<?= e($salon['phone']) ?>" class="text-accent font-semibold">تماس بگیر</a>.
    <?php endif; ?>
  </p>
<?php endif; ?>
