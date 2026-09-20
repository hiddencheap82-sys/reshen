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
    <h2 class="card-title">
      ساعت‌های آزاد — <?= e($selectedDateLabel) ?>
    </h2>
    <?php if (!empty($slots)): ?>
      <span class="text-[11px] text-ink-400 tabular-nums"><?= e(fa_num(count($slots))) ?> وقت</span>
    <?php endif; ?>
  </div>

  <?php if (empty($slots)): ?>
    <div class="glass rounded-2xl py-12 px-5 text-center">
      <?= icon('calendar-x', 'w-10 h-10 mx-auto text-ink-300 mb-3') ?>
      <p class="text-sm font-semibold text-ink-600 mb-1">این روز وقت آزادی ندارد</p>
      <p class="text-[12px] text-ink-400">روز دیگری را از تقویم بالا انتخاب کنید.</p>
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
        /*
         * برش‌ها از App\Support\Clock می‌آید، نه از عددهای محلی. پیش‌تر
         * اینجا مرزِ «عصر» ساعت ۱۶ بود و هر چیزی بعد از آن — حتی ۲۱:۰۰ —
         * عصر شمرده می‌شد؛ در فارسی آن ساعت شب است.
         */
        $groups = ['صبح' => [], 'ظهر' => [], 'عصر' => [], 'شب' => []];
        foreach ($slots as $time) {
            $groups[App\Support\Clock::partOfDay($time)][] = $time;
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
                    <span class="slot glass h-12 grid place-items-center rounded-xl
                                 text-sm font-bold text-ink-800 tabular-nums cursor-pointer
                                 transition-all duration-200 ease-out-soft hover:shadow-lift">
                      <?= e(fa_time($time)) ?>
                    </span>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </fieldset>

      <button type="submit" class="btn-accent metal w-full">
        ادامه
        <?= icon('chevron-end', 'w-4 h-4') ?>
      </button>
    </form>
  <?php endif; ?>
</div>
