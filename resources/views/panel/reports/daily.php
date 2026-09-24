<?php
/**
 * گزارش روزانه.
 *
 * @var string  $date      Y-m-d
 * @var string  $dayLabel  «امروز»، «دیروز» یا «سه‌شنبه ۱ مهر»
 * @var string  $prevDate
 * @var ?string $nextDate  برای امروز null
 * @var bool    $isToday
 * @var array   $totals
 * @var array   $breakdown
 * @var array   $byStaff
 * @var array   $rescued
 */
$methodLabels = ['cash'=>'نقدی','card_to_card'=>'کارت‌به‌کارت','pos'=>'کارتخوان','online'=>'آنلاین'];
?>
<div class="flex items-center justify-between mb-5">
  <h1 class="page-title">گزارش روزانه</h1>
  <a href="<?= e(url('panel/reports/monthly')) ?>"
     class="glass h-11 inline-flex items-center gap-1.5 rounded-xl px-3.5
            text-[12px] font-bold text-accent tap shrink-0">
    گزارش ماهانه
    <?= icon('chevron-end', 'w-3.5 h-3.5') ?>
  </a>
</div>

<!--
  روز قبل و بعد، با یک ضربه.

  پیش‌تر برای دیدنِ دیروز باید سه منوی کشویی (روز، ماه، سال) را عوض
  می‌کردی و «نمایش» را می‌زدی — پنج کار برای سؤالی که هر شب پرسیده
  می‌شود: «دیروز چقدر فروختیم؟». انتخابگر شمسی هنوز هست، برای پریدن به
  روزی دور، ولی پشت «روز دیگر».
-->
<nav class="glass rounded-2xl flex items-center justify-between gap-2 p-1.5 mb-2"
     aria-label="انتخاب روز">
  <a href="<?= e(url('panel/reports?d=' . $prevDate)) ?>"
     class="tap h-11 w-11 grid place-items-center rounded-xl text-ink-600 hover:bg-ink-50"
     aria-label="روز قبل"><?= icon('chevron-start', 'w-4 h-4') ?></a>
  <span class="text-[14px] font-extrabold text-ink-900"><?= e($dayLabel) ?></span>
  <?php if ($nextDate !== null): ?>
    <a href="<?= e(url('panel/reports?d=' . $nextDate)) ?>"
       class="tap h-11 w-11 grid place-items-center rounded-xl text-ink-600 hover:bg-ink-50"
       aria-label="روز بعد"><?= icon('chevron-end', 'w-4 h-4') ?></a>
  <?php else: ?>
    <span class="h-11 w-11" aria-hidden="true"></span>
  <?php endif; ?>
</nav>

<div class="flex items-center justify-between mb-5">
  <details class="group">
    <summary class="tap inline-flex items-center gap-1.5 h-11 text-[12px] font-semibold text-ink-600 cursor-pointer list-none">
      <?= icon('calendar', 'w-4 h-4') ?>
      روز دیگر
    </summary>
    <form method="get" action="<?= e(url('panel/reports')) ?>" class="mt-2 flex flex-wrap items-center gap-2">
      <?php $name = 'date'; $value = $date; $label = 'گزارش'; $years = [-2, 0];
            include BASE_PATH . '/resources/views/components/jalali-date-input.php'; ?>
      <button type="submit" class="btn-ink h-11 text-[13px] px-4">نمایش</button>
    </form>
  </details>
  <?php if (!$isToday): ?>
    <a href="<?= e(url('panel/reports')) ?>"
       class="tap h-11 inline-flex items-center rounded-xl px-3.5 text-[12px] font-bold text-accent">برگشت به امروز</a>
  <?php endif; ?>
</div>

<div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
  <div class="glass rounded-2xl p-4 text-center">
    <div class="text-2xl font-extrabold text-accent"><?= toman((int)$totals['total']) ?></div>
    <div class="text-[12px] text-ink-400 mt-1">فروش کل</div>
  </div>
  <div class="glass rounded-2xl p-4 text-center">
    <div class="text-2xl font-extrabold text-ink-800"><?= fa_num($totals['count']) ?></div>
    <div class="text-[12px] text-ink-400 mt-1">تعداد نوبت</div>
  </div>
  <div class="glass rounded-2xl p-4 text-center">
    <div class="text-2xl font-extrabold text-ink-800"><?= toman((int)$totals['tips']) ?></div>
    <div class="text-[12px] text-ink-400 mt-1">انعام</div>
  </div>
  <div class="rounded-2xl p-4 text-center" style="background:var(--ok-soft)">
    <div class="text-2xl font-extrabold text-ok"><?= fa_num($rescued['count']) ?></div>
    <div class="text-[12px] text-ok mt-1">نجات‌یافته با یادآور</div>
  </div>
</div>

<div class="grid md:grid-cols-2 gap-5">
  <div class="glass rounded-2xl p-5">
    <h2 class="card-title mb-3">به تفکیک روش پرداخت</h2>
    <?php foreach ($breakdown as $b): ?>
    <div class="flex items-center justify-between text-sm py-1.5">
      <span class="text-ink-600"><?= e($methodLabels[$b['method']] ?? $b['method']) ?></span>
      <span class="font-bold text-ink-800"><?= toman((int)$b['total']) ?></span>
    </div>
    <?php endforeach; ?>
    <?php if (empty($breakdown)): ?><p class="text-xs text-ink-400">پرداختی ثبت نشده.</p><?php endif; ?>
  </div>
  <div class="glass rounded-2xl p-5">
    <h2 class="card-title mb-3">به تفکیک آرایشگر</h2>
    <?php foreach ($byStaff as $s): ?>
    <div class="flex items-center justify-between gap-3 text-sm py-1.5">
      <span class="min-w-0 truncate text-ink-700">
        <?= e($s['name']) ?>
        <span class="text-[12px] text-ink-400">· <?= (int) $s['count'] > 0 ? e(fa_num($s['count'])) . ' نوبت' : 'بدون نوبت' ?></span>
      </span>
      <span class="font-bold tabular-nums <?= (int) $s['total'] > 0 ? 'text-ink-800' : 'text-ink-400' ?>"><?= toman((int)$s['total']) ?></span>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<?php if ($rescued['count'] > 0): ?>
<div class="rounded-2xl p-5 mt-5 text-sm text-ok" style="background:var(--ok-soft)">
  <?= fa_num($rescued['count']) ?> نوبتی که با پیامک یادآور نجات پیدا کرد ≈ <?= toman((int)$rescued['value']) ?>
</div>
<?php endif; ?>
