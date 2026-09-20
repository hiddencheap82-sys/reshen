<?php
/**
 * @var array $salon
 * @var array $calendar
 * @var array $minMonth
 * @var string $selectedDate
 * @var string $selectedDateLabel
 * @var array $slots
 */

$base = 's/' . $salon['slug'] . '/slots';
?>

<h1 class="text-base font-bold text-slate-800 mb-1">انتخاب زمان</h1>
<p class="text-sm text-slate-500 mb-4">اول روز را انتخاب کنید، بعد ساعت.</p>

<?php
// گام ۱ — تقویم
echo App\Core\View::render('components.jalali-calendar', [
    'cal' => $calendar,
    'selected' => $selectedDate,
    'minMonth' => $minMonth,
    'linkFor' => static fn (string $g): string => url(
        $base . '?date=' . $g . '&jy=' . $calendar['year'] . '&jm=' . $calendar['month']
    ),
    'navFor' => static fn (int $y, int $m): string => url(
        $base . '?date=' . $selectedDate . '&jy=' . $y . '&jm=' . $m
    ),
]);
?>

<div class="mt-5">
  <div class="flex items-baseline justify-between mb-2">
    <h2 class="text-sm font-bold text-slate-800">
      ساعت‌های آزاد — <?= e($selectedDateLabel) ?>
    </h2>
    <?php if (!empty($slots)): ?>
      <span class="text-xs text-slate-400"><?= e(fa_num(count($slots))) ?> وقت</span>
    <?php endif; ?>
  </div>

  <?php if (empty($slots)): ?>
    <div class="bg-white border border-slate-200 rounded-2xl py-10 px-5 text-center">
      <svg class="w-10 h-10 mx-auto text-slate-300 mb-3" fill="none" viewBox="0 0 24 24"
           stroke="currentColor" stroke-width="1.5" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round"
              d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
      </svg>
      <p class="text-sm font-semibold text-slate-600 mb-1">این روز وقت آزادی ندارد</p>
      <p class="text-xs text-slate-400">روز دیگری را از تقویم بالا انتخاب کنید.</p>
    </div>
  <?php else: ?>
    <form method="post" action="<?= e(url($base)) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="date" value="<?= e($selectedDate) ?>">

      <fieldset>
        <legend class="sr-only">انتخاب ساعت برای <?= e($selectedDateLabel) ?></legend>

        <div class="grid grid-cols-3 gap-2 mb-5">
          <?php foreach ($slots as $time): ?>
            <label class="relative">
              <input type="radio" name="time" value="<?= e($time) ?>" required
                     class="peer sr-only">
              <span class="h-12 grid place-items-center rounded-xl border border-slate-200
                           text-sm font-semibold text-slate-700 tabular-nums cursor-pointer
                           transition-colors hover:bg-slate-50
                           peer-checked:bg-brand-600 peer-checked:text-white peer-checked:border-brand-600
                           peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2
                           peer-focus-visible:outline-brand-600">
                <?= e(fa_num($time)) ?>
              </span>
            </label>
          <?php endforeach; ?>
        </div>
      </fieldset>

      <button type="submit"
              class="w-full h-12 bg-brand-600 hover:bg-brand-700 text-white font-bold rounded-xl
                     transition-colors cursor-pointer
                     focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600">
        ادامه
      </button>
    </form>
  <?php endif; ?>
</div>
