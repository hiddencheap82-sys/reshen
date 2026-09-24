<?php
/**
 * سلامت سالن‌ها.
 *
 * صفحه‌ای که وقتی چیزی خراب است باز می‌شود، پس هیچ چیزِ تزئینی
 * ندارد: هر ردیف یک سالن، و زیرش دقیقاً چه چیزی خراب است و کجا درست
 * می‌شود. بدترین‌ها بالا.
 *
 * @var array $entries
 * @var array $summary
 * @var bool $onlyBroken
 */
$active = 'health';
include __DIR__ . '/_nav.php';

$style = [
    'blocking' => ['کار نمی‌کند', 'text-bad', 'var(--bad-soft)', 'alert'],
    'warning' => ['هشدار', 'text-warn', 'var(--accent-soft)', 'alert'],
    'info' => ['خبر', 'text-ink-500', 'var(--fill-secondary)', 'info'],
];
?>

<div class="flex items-start justify-between gap-3 mb-4">
  <div>
    <h1 class="page-title">سلامت سالن‌ها</h1>
    <p class="text-[12px] text-ink-400 mt-1 max-w-[52ch] leading-relaxed">
      خرابی‌های واقعی بی‌صدایند: سالنی که آرایشگر فعال ندارد هیچ خطایی نمی‌دهد،
      فقط هیچ سانسی ندارد و مشتری فکر می‌کند پر است.
    </p>
  </div>
</div>

<!-- خلاصه: سه عدد، چون سه تصمیمِ متفاوت‌اند -->
<div class="grid grid-cols-3 gap-2 mb-5">
  <div class="glass rounded-2xl p-3.5 text-center">
    <div class="text-[22px] font-extrabold tabular-nums text-bad"><?= e(fa_num($summary['blocking'])) ?></div>
    <div class="text-[12px] text-ink-500 mt-0.5">از کار افتاده</div>
  </div>
  <div class="glass rounded-2xl p-3.5 text-center">
    <div class="text-[22px] font-extrabold tabular-nums text-warn"><?= e(fa_num($summary['warning'])) ?></div>
    <div class="text-[12px] text-ink-500 mt-0.5">هشدار دارد</div>
  </div>
  <div class="glass rounded-2xl p-3.5 text-center">
    <div class="text-[22px] font-extrabold tabular-nums text-ink-800"><?= e(fa_num($summary['healthy'])) ?></div>
    <div class="text-[12px] text-ink-500 mt-0.5">سالم</div>
  </div>
</div>

<div class="flex items-center gap-2 mb-4">
  <a href="<?= e(url('platform/health')) ?>"
     class="tap h-11 px-3.5 inline-flex items-center rounded-xl text-[12px] font-semibold
            <?= $onlyBroken ? 'day-chip-on' : 'glass text-ink-700' ?>">فقط ایراددارها</a>
  <a href="<?= e(url('platform/health?all=1')) ?>"
     class="tap h-11 px-3.5 inline-flex items-center rounded-xl text-[12px] font-semibold
            <?= $onlyBroken ? 'glass text-ink-700' : 'day-chip-on' ?>">همهٔ سالن‌ها</a>
</div>

<?php if ($entries === []): ?>
  <div class="glass rounded-2xl py-12 px-5 text-center">
    <?= icon('check', 'w-9 h-9 mx-auto text-ink-400 mb-3') ?>
    <p class="text-sm font-bold text-ink-700 mb-1">هیچ سالنی ایراد ندارد</p>
    <p class="text-[12px] text-ink-400">همه آرایشگر، خدمت و ساعت کاری دارند.</p>
  </div>
<?php else: ?>
  <div class="space-y-3">
    <?php foreach ($entries as $entry): ?>
      <?php $s = $entry['salon']; ?>
      <section class="glass rounded-2xl overflow-hidden
                      <?= $entry['worst'] === 'blocking' ? 'hairline-bad' : '' ?>">
        <a href="<?= e(url('platform/' . $s['id'])) ?>"
           class="tap flex items-center gap-3 px-4 py-3 hover:bg-ink-50">
          <span class="flex-1 min-w-0">
            <span class="block text-[14px] font-bold text-ink-900 truncate"><?= e($s['name']) ?></span>
            <span class="block text-[12px] text-ink-400 truncate">
              <?php if (($s['city'] ?? '') !== ''): ?><?= e($s['city']) ?> · <?php endif; ?>
              <span dir="ltr">/s/<?= e($s['slug']) ?></span>
            </span>
          </span>
          <?php if ($entry['issues'] === []): ?>
            <span class="text-[12px] font-semibold text-ink-400 shrink-0">سالم</span>
          <?php else: ?>
            <span class="text-[12px] font-bold shrink-0 <?= $style[$entry['worst']][1] ?>">
              <?= e(fa_num(count($entry['issues']))) ?> مورد
            </span>
          <?php endif; ?>
          <?= icon('chevron-end', 'w-4 h-4 text-ink-400 shrink-0') ?>
        </a>

        <?php if ($entry['issues'] !== []): ?>
          <div class="border-t" style="border-color:var(--line)">
            <?php foreach ($entry['issues'] as $issue): ?>
              <?php [$label, $textClass, $bg, $iconName] = $style[$issue['level']]; ?>
              <div class="flex items-start gap-3 px-4 py-3 border-b last:border-b-0"
                   style="border-color:var(--line)">
                <span class="w-8 h-8 shrink-0 rounded-xl grid place-items-center <?= $textClass ?>"
                      style="background:<?= $bg ?>" aria-hidden="true">
                  <?= icon($iconName, 'w-4 h-4') ?>
                </span>
                <div class="min-w-0">
                  <p class="text-[13px] font-bold text-ink-900"><?= e($issue['title']) ?></p>
                  <p class="text-[12px] text-ink-500 mt-0.5 leading-relaxed"><?= e($issue['fix']) ?></p>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
