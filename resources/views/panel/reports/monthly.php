<?php
/**
 * گزارش ماهانه.
 *
 * @var int   $jy
 * @var int   $jm
 * @var array $totals
 * @var array $breakdown
 * @var array $rescued
 * @var array $days  هر روزِ ماه: day, date, total, visits, isToday, isFuture
 * @var array $prev  jy, jm
 * @var ?array $next jy, jm — ماهِ آینده null است
 */
$methodLabels = ['cash'=>'نقدی','card_to_card'=>'کارت‌به‌کارت','pos'=>'کارتخوان','online'=>'آنلاین'];
$monthNames = [1=>'فروردین',2=>'اردیبهشت',3=>'خرداد',4=>'تیر',5=>'مرداد',6=>'شهریور',7=>'مهر',8=>'آبان',9=>'آذر',10=>'دی',11=>'بهمن',12=>'اسفند'];

/*
 * سقفِ محور: عددی گرد بالای بیشترین فروش (۱، ۲، ۲٫۵ یا ۵ ضربدر توانی از
 * ده). «۲۵۰ هزار» خواندنی است؛ «۲۴۳٬۶۰۰» نه.
 */
$maxRials = max(array_map(static fn ($d) => $d['total'], $days) ?: [0]);
$maxToman = (int) ceil($maxRials / 10);
$axisToman = 0;
if ($maxToman > 0) {
    $mag = 10 ** (int) floor(log10($maxToman));
    foreach ([1, 2, 2.5, 5, 10] as $step) {
        if ($step * $mag >= $maxToman) {
            $axisToman = (int) ($step * $mag);
            break;
        }
    }
}
$compact = static function (int $toman): string {
    if ($toman >= 1000000) {
        return fa_num(round($toman / 1000000, 1)) . ' میلیون';
    }
    if ($toman >= 1000) {
        return fa_num((int) round($toman / 1000)) . ' هزار';
    }

    return fa_num($toman);
};

$best = null;
foreach ($days as $d) {
    if ($d['total'] > 0 && ($best === null || $d['total'] > $best['total'])) {
        $best = $d;
    }
}
$monthLabel = $monthNames[$jm] . ' ' . fa_num($jy);
?>
<div class="flex items-center justify-between gap-3 mb-4">
  <h1 class="page-title">گزارش ماهانه</h1>
  <a href="<?= e(url('panel/reports')) ?>"
     class="glass h-11 inline-flex items-center gap-1.5 rounded-xl px-3.5
            text-[12px] font-bold text-accent tap shrink-0">
    گزارش روزانه
    <?= icon('chevron-end', 'w-3.5 h-3.5') ?>
  </a>
</div>

<!--
  ماهِ قبل و بعد.

  پیش‌تر گزارش ماهانه فقط همین ماه را نشان می‌داد؛ روز دوم مهر، تقریباً
  همهٔ تاریخچهٔ سالن در شهریور بود و هیچ راهی به آن نبود.
-->
<nav class="glass rounded-2xl flex items-center justify-between gap-2 p-1.5 mb-5"
     aria-label="انتخاب ماه">
  <a href="<?= e(url('panel/reports/monthly?jy=' . $prev['jy'] . '&jm=' . $prev['jm'])) ?>"
     class="tap h-11 inline-flex items-center gap-1 rounded-xl px-3 text-[12px] font-semibold text-ink-600 hover:bg-ink-50">
    <?= icon('chevron-start', 'w-4 h-4') ?>
    <?= e($monthNames[$prev['jm']]) ?>
  </a>
  <span class="text-[14px] font-extrabold text-ink-900"><?= e($monthLabel) ?></span>
  <?php if ($next !== null): ?>
    <a href="<?= e(url('panel/reports/monthly?jy=' . $next['jy'] . '&jm=' . $next['jm'])) ?>"
       class="tap h-11 inline-flex items-center gap-1 rounded-xl px-3 text-[12px] font-semibold text-ink-600 hover:bg-ink-50">
      <?= e($monthNames[$next['jm']]) ?>
      <?= icon('chevron-end', 'w-4 h-4') ?>
    </a>
  <?php else: ?>
    <!-- جای خالیِ هم‌اندازه تا نام ماه وسط بماند -->
    <span class="h-11 px-3 text-[12px] invisible" aria-hidden="true">ماه بعد</span>
  <?php endif; ?>
</nav>

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

<section class="glass rounded-2xl p-5 mb-5">
  <h2 class="card-title mb-1">فروش روزانه</h2>
  <?php if ($best !== null): ?>
    <!--
      خطِ بازخوانی.

      به‌جای راهنمای شناور — که روی گوشی سرتیتر را می‌پوشاند — همین خط
      عوض می‌شود: پیش‌فرض بهترین روز را می‌گوید، و وقتی انگشت روی
      نمودار است، همان روزی را که زیر انگشت است. همان الگوی
      برنامه‌های «سلامت» و «سهام» اپل. aria-live تا صفحه‌خوان هم بشنود.
    -->
    <p class="text-[12px] text-ink-500 mb-4 min-h-[1.25rem] tabular-nums" id="sales-readout" aria-live="polite"
       data-default="بهترین روز: <?= e(fa_num($best['day']) . ' ' . $monthNames[$jm]) ?> — <?= e(toman($best['total'])) ?>">
      بهترین روز: <?= e(fa_num($best['day']) . ' ' . $monthNames[$jm]) ?> — <?= e(toman($best['total'])) ?>
    </p>
  <?php endif; ?>

  <?php if ($best === null): ?>
    <p class="text-[13px] text-ink-500 py-6 text-center">
      <?= e($monthLabel) ?> هنوز فروشی ثبت نکرده.
    </p>
  <?php else: ?>
    <div class="relative" id="sales-chart">
      <!-- سقف محور: یک خط راهنما و یک عدد گرد، سمت راست (شروعِ خواندن) -->
      <div class="flex items-center gap-2 mb-1.5 text-[11px] text-ink-400 tabular-nums">
        <span><?= e($compact($axisToman)) ?> تومان</span>
      </div>

      <div class="chart-plot" tabindex="0" role="group"
           aria-label="فروش روزانهٔ <?= e($monthLabel) ?>. با کلیدهای جهت بین روزها جابه‌جا شوید.">
        <div class="chart-grid" style="top:0"></div>
        <div class="chart-grid" style="bottom:0"></div>
        <div class="chart-bars">
          <?php foreach ($days as $i => $d): ?>
            <?php
            $pct = $axisToman > 0 ? min(100, ($d['total'] / 10) / $axisToman * 100) : 0;
            $tip = fa_num($d['day']) . ' ' . $monthNames[$jm] . '|'
                 . ($d['isFuture'] ? 'هنوز نیامده' : ($d['total'] > 0 ? toman($d['total']) : 'بدون فروش')) . '|'
                 . ($d['visits'] > 0 ? fa_num($d['visits']) . ' نوبت' : '');
            ?>
            <div class="chart-slot" data-i="<?= $i ?>" data-tip="<?= e($tip) ?>" aria-selected="false">
              <?php if ($d['total'] > 0): ?>
                <span class="chart-bar" style="height:<?= number_format(max(2.0, $pct), 1, '.', '') ?>%"></span>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!--
        برچسب روزها: فقط چند تا، نه همه. سی‌ویک عدد زیر سی‌ویک میلهٔ
        ده‌پیکسلی روی هم می‌افتند و هیچ‌کدام خوانده نمی‌شوند.
      -->
      <div class="chart-x" aria-hidden="true">
        <?php $last = count($days); ?>
        <?php foreach ($days as $d): ?>
          <?php $show = in_array($d['day'], [1, 5, 10, 15, 20, 25, $last], true) || $d['isToday']; ?>
          <span class="<?= $d['isToday'] ? 'is-today' : '' ?>"><?= $show ? e(fa_num($d['day'])) : '' ?></span>
        <?php endforeach; ?>
      </div>

    </div>

    <!-- همتای جدولی نمودار — برای صفحه‌خوان و برای کسی که عدد دقیق می‌خواهد -->
    <details class="mt-4">
      <summary class="tap inline-flex items-center gap-1.5 h-11 text-[12px] font-semibold text-ink-600 cursor-pointer list-none">
        <?= icon('chart', 'w-4 h-4') ?>
        جدول روزها
      </summary>
      <table class="w-full text-[12px] mt-2">
        <thead>
          <tr class="text-ink-400">
            <th class="text-right font-semibold py-1.5">روز</th>
            <th class="text-center font-semibold py-1.5">نوبت</th>
            <th class="text-left font-semibold py-1.5">فروش</th>
          </tr>
        </thead>
        <tbody class="tabular-nums">
          <?php foreach ($days as $d): ?>
            <?php if ($d['total'] <= 0) { continue; } ?>
            <tr class="border-t" style="border-color:var(--line)">
              <td class="py-1.5 text-ink-700"><?= e(fa_num($d['day']) . ' ' . $monthNames[$jm]) ?></td>
              <td class="py-1.5 text-center text-ink-600"><?= e(fa_num($d['visits'])) ?></td>
              <td class="py-1.5 text-left font-bold text-ink-800"><?= e(toman($d['total'])) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </details>

    <script>
    /*
     * راهنمای لمسی.
     *
     * میله‌ها روی گوشی ده پیکسل پهنا دارند — هدفِ لمسیِ ده‌پیکسلی را
     * هیچ انگشتی نمی‌زند. پس به‌جای «روی میله بزن»، هر جای نمودار که
     * انگشت یا نشانگر باشد، نزدیک‌ترین روز انتخاب می‌شود؛ کشیدن انگشت
     * روی نمودار بین روزها می‌لغزد. صفحه‌کلید هم با کلیدهای جهت.
     */
    (function () {
      var plot = document.querySelector('#sales-chart .chart-plot');
      var readout = document.getElementById('sales-readout');
      if (!plot || !readout) return;
      var slots = [].slice.call(plot.querySelectorAll('.chart-slot'));
      var fallback = readout.getAttribute('data-default') || '';
      var current = -1;

      function select(i) {
        if (i < 0 || i >= slots.length || i === current) return;
        if (current >= 0) slots[current].setAttribute('aria-selected', 'false');
        current = i;
        slots[i].setAttribute('aria-selected', 'true');

        var parts = (slots[i].getAttribute('data-tip') || '').split('|').filter(Boolean);
        readout.textContent = parts.join(' — ');
        readout.classList.add('text-ink-800', 'font-bold');
      }

      function reset() {
        if (current >= 0) slots[current].setAttribute('aria-selected', 'false');
        current = -1;
        readout.textContent = fallback;
        readout.classList.remove('text-ink-800', 'font-bold');
      }

      function nearest(clientX) {
        var best = 0, bestD = Infinity;
        slots.forEach(function (sl, i) {
          var r = sl.getBoundingClientRect();
          var d = Math.abs(r.left + r.width / 2 - clientX);
          if (d < bestD) { bestD = d; best = i; }
        });
        return best;
      }

      plot.addEventListener('pointermove', function (e) { select(nearest(e.clientX)); });
      plot.addEventListener('pointerdown', function (e) { select(nearest(e.clientX)); });
      plot.addEventListener('pointerleave', reset);
      plot.addEventListener('blur', reset);
      plot.addEventListener('keydown', function (e) {
        // راست‌به‌چپ: روز ۱ سمت راست است، پس «چپ» یعنی روز بعد.
        if (e.key === 'ArrowLeft') { e.preventDefault(); select(Math.min(slots.length - 1, Math.max(0, current + 1))); }
        if (e.key === 'ArrowRight') { e.preventDefault(); select(Math.max(0, current - 1)); }
        if (e.key === 'Escape') { reset(); }
      });
    })();
    </script>
  <?php endif; ?>
</section>

<div class="glass rounded-2xl p-5">
  <h2 class="card-title mb-3">به تفکیک روش پرداخت</h2>
  <?php foreach ($breakdown as $b): ?>
  <div class="flex items-center justify-between text-sm py-1.5">
    <span class="text-ink-600"><?= e($methodLabels[$b['method']] ?? $b['method']) ?></span>
    <span class="font-bold text-ink-800"><?= toman((int)$b['total']) ?></span>
  </div>
  <?php endforeach; ?>
</div>
