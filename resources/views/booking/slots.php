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

<div class="rise mb-4">
  <h1 class="text-[15px] font-extrabold text-ink-900 mb-1">کِی می‌آیی؟</h1>
  <p class="text-[13px] text-ink-500">اول روز را از تقویم انتخاب کن، بعد ساعت.</p>
</div>

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

<div class="mt-5 rise rise-2">
  <div class="flex items-baseline justify-between mb-2">
    <h2 class="text-[14px] font-extrabold text-ink-900">
      ساعت‌های آزاد — <?= e($selectedDateLabel) ?>
    </h2>
    <?php if (!empty($slots)): ?>
      <span class="text-[11.5px] text-ink-400 tabular-nums"><?= e(fa_num(count($slots))) ?> وقت</span>
    <?php endif; ?>
  </div>

  <?php if (empty($slots)): ?>
    <div class="surface rounded-2xl py-12 px-5 text-center shadow-card">
      <svg class="w-10 h-10 mx-auto text-ink-300 mb-3" fill="none" viewBox="0 0 24 24"
           stroke="currentColor" stroke-width="1.5" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round"
              d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
      </svg>
      <p class="text-sm font-semibold text-ink-600 mb-1">این روز وقت آزادی ندارد</p>
      <p class="text-[12.5px] text-ink-400">روز دیگری را از تقویم بالا انتخاب کنید.</p>
    </div>
  <?php else: ?>
    <form method="post" action="<?= e(url($base)) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="date" value="<?= e($selectedDate) ?>">

      <fieldset>
        <legend class="sr-only">انتخاب ساعت برای <?= e($selectedDateLabel) ?></legend>

        <?php
        /*
         * ساعت‌ها را به صبح/ظهر/عصر گروه می‌کنیم.
         *
         * چرا: یک سالن ۱۲ ساعته با بازهٔ ۱۵ دقیقه‌ای، بیش از ۴۰ دکمه
         * می‌سازد. دیوارِ دکمه، انتخاب را سخت‌تر می‌کند نه آسان‌تر —
         * مشتری معمولاً اول می‌داند «عصر می‌آیم»، بعد ساعت دقیق را
         * انتخاب می‌کند.
         */
        $groups = ['صبح' => [], 'ظهر' => [], 'عصر' => []];
        foreach ($slots as $time) {
            $hour = (int) substr($time, 0, 2);
            $key = $hour < 12 ? 'صبح' : ($hour < 16 ? 'ظهر' : 'عصر');
            $groups[$key][] = $time;
        }
        $index = 0;
        ?>

        <div class="space-y-4 mb-5">
          <?php foreach ($groups as $label => $times): ?>
            <?php if ($times === []) { continue; } ?>
            <div>
              <div class="flex items-center gap-2 mb-2">
                <h3 class="text-[12px] font-bold text-ink-500"><?= e($label) ?></h3>
                <span class="flex-1 h-px" style="background:var(--line)" aria-hidden="true"></span>
                <span class="text-[11px] text-ink-400 tabular-nums"><?= e(fa_num(count($times))) ?></span>
              </div>

              <div class="grid grid-cols-3 gap-2">
                <?php foreach ($times as $time): $index++; ?>
                  <label class="pick relative block tap rise rise-<?= min((int) ceil($index / 6), 5) ?>">
                    <input type="radio" name="time" value="<?= e($time) ?>" required class="sr-only">
                    <span class="slot surface h-12 grid place-items-center rounded-xl shadow-card
                                 text-sm font-bold text-ink-800 tabular-nums cursor-pointer
                                 transition-all duration-200 ease-out-soft hover:shadow-lift">
                      <?= e(fa_num($time)) ?>
                    </span>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </fieldset>

      <button type="submit" class="btn-gold w-full">
        ادامه
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"
             stroke-width="2.5" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
        </svg>
      </button>
    </form>
  <?php endif; ?>
</div>
