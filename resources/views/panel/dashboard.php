<?php
/**
 * داشبورد سالن — «امروز در یک نگاه».
 *
 * ترتیب بخش‌ها عمدی است و از پنل پلتفرم آمده: اول چیزی که باید
 * *کاری* برایش کرد، بعد عددهای امروز، بعد روند. داشبوردی که با
 * عددِ تزئینی شروع شود، هر روز باز می‌شود و هیچ‌وقت به کاری ختم
 * نمی‌شود.
 *
 * @var string     $date
 * @var bool       $isToday
 * @var array      $attention
 * @var array      $today
 * @var array|null $rightNow
 * @var array      $byStaff
 * @var array      $days
 * @var int        $weekTotal
 * @var int        $prevWeekTotal
 * @var array      $busiestHours
 * @var array      $sleeping
 */
$hasAttention = $attention['unpaid'] > 0 || $attention['no_shows'] > 0 || $attention['stale'] > 0;
$maxDay = max(array_map(static fn ($d) => $d['total'], $days) ?: [0]);
$peak = $busiestHours === [] ? null : array_reduce(
    $busiestHours,
    static fn ($best, $h) => $best === null || $h['count'] > $best['count'] ? $h : $best
);
$maxHour = $busiestHours === [] ? 0 : max(array_map(static fn ($h) => $h['count'], $busiestHours));

// درصد رشد فقط وقتی معنی دارد که هفتهٔ قبل صفر نبوده باشد؛ «بی‌نهایت
// درصد رشد» نسبت به صفر، عددی است که هیچ تصمیمی از آن درنمی‌آید.
$growth = $prevWeekTotal > 0 ? (int) round(($weekTotal - $prevWeekTotal) / $prevWeekTotal * 100) : null;
?>

<div class="flex items-center justify-between gap-2 mb-1">
  <h1 class="page-title">داشبورد</h1>
  <a href="<?= e(url('panel/reports')) ?>"
     class="glass h-11 inline-flex items-center gap-1.5 rounded-xl px-3.5
            text-[12px] font-bold text-accent tap shrink-0">
    گزارش کامل
    <?= icon('chevron-end', 'w-3.5 h-3.5') ?>
  </a>
</div>
<p class="text-[13px] text-ink-400 mb-5">
  <?= e(jdate($date . ' 00:00:00', 'D j M Y')) ?>
  <?php if (!$isToday): ?>
    · <a href="<?= e(url('panel/dashboard')) ?>" class="text-accent font-bold">برگرد به امروز</a>
  <?php endif; ?>
</p>

<?php if ($hasAttention): ?>
  <!--
    فقط وقتی چیزی هست. کارتِ همیشه‌حاضرِ «همه‌چیز خوب است» بعد از یک
    هفته نادیده گرفته می‌شود، و آن‌وقت روزی که واقعاً چیزی هست هم
    دیده نمی‌شود.
  -->
  <section class="glass rounded-2xl p-4 mb-5 hairline-accent">
    <h2 class="card-title mb-3">نیاز به رسیدگی</h2>

    <?php
    $rows = [];
    if ($attention['unpaid'] > 0) {
        $rows[] = ['wallet', 'text-bad', 'var(--bad-soft)',
            fa_num($attention['unpaid']) . ' نوبت تمام‌شده، تسویه‌نشده',
            'کار انجام شده ولی پولش ثبت نشده — گزارش فروش امروز کم‌تر از واقعیت است.',
            url('panel')];
    }
    if ($attention['stale'] > 0) {
        $rows[] = ['clock', 'text-warn', 'var(--accent-soft)',
            fa_num($attention['stale']) . ' نوبت که ساعتش گذشته و هنوز در صف است',
            'یا مشتری نیامده و کسی «غیبت» نزده، یا کار شروع شده و ثبت نشده. تا ثبت نشود موتور تخمین چیزی یاد نمی‌گیرد.',
            url('panel')];
    }
    if ($attention['no_shows'] > 0) {
        $rows[] = ['user-x', 'text-ink-500', 'var(--fill-secondary)',
            fa_num($attention['no_shows']) . ' غیبت امروز',
            'یک تماس کوتاه معمولاً نوبت بعدی را برمی‌گرداند.',
            url('panel/customers')];
    }
    foreach ($rows as [$ic, $tone, $bg, $title, $note, $href]):
    ?>
      <a href="<?= e($href) ?>" class="tap flex items-start gap-3 rounded-xl px-3 py-2.5 hover:bg-ink-50">
        <span class="w-9 h-9 shrink-0 rounded-xl grid place-items-center <?= $tone ?>"
              style="background:<?= $bg ?>" aria-hidden="true"><?= icon($ic, 'w-4 h-4') ?></span>
        <span class="flex-1 min-w-0">
          <span class="block text-[13px] font-bold text-ink-900"><?= e($title) ?></span>
          <span class="block text-[12px] text-ink-400 leading-relaxed mt-0.5"><?= e($note) ?></span>
        </span>
        <?= icon('chevron-end', 'w-4 h-4 text-ink-400 mt-2.5') ?>
      </a>
    <?php endforeach; ?>
  </section>
<?php endif; ?>

<?php if ($rightNow !== null && ($rightNow['in_chair'] > 0 || $rightNow['waiting'] > 0)): ?>
  <!-- «الان» فقط برای امروز. در تاریخ گذشته این بخش اصلاً رندر نمی‌شود. -->
  <a href="<?= e(url('panel')) ?>"
     class="tap glass rounded-2xl p-4 mb-5 flex items-center gap-3 hover:shadow-lift">
    <span class="w-11 h-11 shrink-0 rounded-2xl grid place-items-center text-accent"
          style="background:var(--accent-soft)" aria-hidden="true"><?= icon('queue-fill', 'w-5 h-5') ?></span>
    <span class="flex-1 min-w-0">
      <span class="block text-[13px] font-bold text-ink-900">
        <?php if ($rightNow['in_chair'] > 0): ?>
          <?= e(fa_num($rightNow['in_chair'])) ?> نفر روی صندلی
        <?php endif; ?>
        <?php if ($rightNow['in_chair'] > 0 && $rightNow['waiting'] > 0): ?>·<?php endif; ?>
        <?php if ($rightNow['waiting'] > 0): ?>
          <?= e(fa_num($rightNow['waiting'])) ?> نفر منتظر
        <?php endif; ?>
      </span>
      <span class="block text-[12px] text-ink-400 mt-0.5">
        <?php if ($rightNow['last_eta'] !== null): ?>
          آخرین نفر حدود <?= e(fa_time_label(substr((string) $rightNow['last_eta'], 11, 5))) ?> نوبتش می‌شود
        <?php else: ?>
          رفتن به صف زنده
        <?php endif; ?>
      </span>
    </span>
    <?= icon('chevron-end', 'w-4 h-4 text-ink-400') ?>
  </a>
<?php endif; ?>

<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
  <?php
  $cards = [
      ['فروش امروز', toman($today['revenue']), 'text-accent',
       $today['tips'] > 0 ? toman($today['tips']) . ' انعام' : null],
      ['نوبت امروز', fa_num($today['appointments']), 'text-ink-800',
       fa_num($today['completed']) . ' انجام شد'],
      ['مراجع حضوری', fa_num($today['walkin']), 'text-ink-800',
       $today['appointments'] > 0
           ? fa_num((int) round($today['walkin'] / $today['appointments'] * 100)) . '٪ از کل'
           : null],
      ['مشتری تازه', fa_num($today['new_customers']), 'text-ink-800', null],
  ];
  foreach ($cards as [$label, $value, $tone, $sub]):
  ?>
    <div class="glass rounded-2xl p-4">
      <div class="text-2xl font-extrabold tabular-nums <?= $tone ?>"><?= e($value) ?></div>
      <div class="text-[12px] text-ink-500 mt-1"><?= e($label) ?></div>
      <?php if ($sub !== null): ?>
        <div class="text-[12px] text-ink-400 mt-0.5"><?= e($sub) ?></div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>

<div class="grid lg:grid-cols-2 gap-4 mb-5">

  <section class="glass rounded-2xl p-4">
    <div class="flex items-baseline justify-between mb-1">
      <h2 class="card-title">هفت روز گذشته</h2>
      <span class="text-[13px] font-extrabold text-ink-800 tabular-nums"><?= e(toman($weekTotal)) ?></span>
    </div>
    <?php if ($growth !== null): ?>
      <p class="text-[12px] mb-3 <?= $growth >= 0 ? 'text-ok' : 'text-bad' ?>">
        <?= $growth >= 0 ? '▲' : '▼' ?>
        <?= e(fa_num(abs($growth))) ?>٪ نسبت به هفتهٔ قبل
        <span class="text-ink-400">(<?= e(toman($prevWeekTotal)) ?>)</span>
      </p>
    <?php else: ?>
      <p class="text-[12px] text-ink-400 mb-3">هفتهٔ قبل فروشی ثبت نشده، پس مقایسه‌ای در کار نیست.</p>
    <?php endif; ?>

    <!--
      نمودار با div، نه canvas یا کتابخانه: هفت میله ارزش یک فایل
      جاوااسکریپت اضافه را ندارد، و روی اینترنت موبایلِ ایران هر
      کیلوبایت حس می‌شود.

      ارتفاع حداقلی ۳ پیکسل دارد تا روزِ بی‌فروش هم جای خودش را نشان
      بدهد؛ میلهٔ صفرْ نامرئی است و شکافِ نمودار را با «داده نداریم»
      اشتباه می‌گیرند.
    -->
    <div class="flex items-end justify-between gap-1.5 h-24" role="img"
         aria-label="نمودار فروش هفت روز گذشته">
      <?php foreach ($days as $d): ?>
        <?php $h = $maxDay > 0 ? max(3, (int) round($d['total'] / $maxDay * 96)) : 3; ?>
        <div class="flex-1 flex flex-col items-center gap-1 min-w-0">
          <div class="w-full rounded-t-md transition-[height]"
               style="height:<?= $h ?>px;background:var(--accent);opacity:<?= $d['total'] > 0 ? '1' : '.22' ?>"
               title="<?= e($d['weekday'] . ' — ' . toman($d['total'])) ?>"></div>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="flex items-start justify-between gap-1.5 mt-1.5">
      <?php foreach ($days as $d): ?>
        <div class="flex-1 text-center min-w-0">
          <div class="text-[11px] text-ink-400 truncate"><?= e(mb_substr($d['weekday'], 0, 1)) ?></div>
          <div class="text-[11px] text-ink-400 tabular-nums"><?= e($d['label']) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="glass rounded-2xl p-4">
    <h2 class="card-title mb-3">آرایشگرها امروز</h2>
    <?php if ($byStaff === []): ?>
      <p class="text-[12px] text-ink-400">
        هنوز آرایشگری ثبت نشده.
        <a href="<?= e(url('panel/staff')) ?>" class="text-accent font-bold">افزودن آرایشگر</a>
      </p>
    <?php else: ?>
      <?php $topStaff = max(array_map(static fn ($s) => (int) $s['total'], $byStaff) ?: [0]); ?>
      <ul class="space-y-2.5">
        <?php foreach ($byStaff as $s): ?>
          <li>
            <div class="flex items-baseline justify-between gap-2 text-[13px] mb-1">
              <span class="min-w-0 truncate font-semibold text-ink-800"><?= e($s['name']) ?></span>
              <span class="shrink-0 tabular-nums text-ink-500">
                <?= e(fa_num((int) $s['done'])) ?> نفر ·
                <span class="font-bold text-ink-800"><?= e(toman((int) $s['total'])) ?></span>
              </span>
            </div>
            <!-- نوار نسبت، نه عدد تنها: چشم نسبت را سریع‌تر از عدد می‌خواند. -->
            <div class="h-1.5 rounded-full overflow-hidden" style="background:var(--fill-secondary)">
              <div class="h-full rounded-full"
                   style="width:<?= $topStaff > 0 ? (int) round((int) $s['total'] / $topStaff * 100) : 0 ?>%;
                          background:<?= e($s['color'] ?: 'var(--accent)') ?>"></div>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
</div>

<div class="grid lg:grid-cols-2 gap-4">

  <?php if ($peak !== null): ?>
    <section class="glass rounded-2xl p-4">
      <div class="flex items-baseline justify-between mb-1">
        <h2 class="card-title">شلوغ‌ترین ساعت‌ها</h2>
        <span class="text-[12px] text-ink-400">۳۰ روز گذشته</span>
      </div>
      <p class="text-[12px] text-ink-400 mb-3">
        اوج کار ساعت <span class="font-bold text-ink-700"><?= e(fa_num($peak['hour'])) ?></span> است —
        شیفت آرایشگرها را همان‌جا سنگین‌تر بگیرید.
      </p>
      <!--
        میله‌های غیراوج رنگِ کم‌رنگِ لهجه دارند، نه خاکستریِ پُرکننده.
        با خاکستری، میله مثل *جای خالیِ* یک میله دیده می‌شد نه مثل
        داده — چشم آن را زمینه می‌خواند و فقط یک ستون را داده.
      -->
      <div class="flex items-end gap-0.5 h-16" role="img" aria-label="نمودار شلوغی ساعت‌ها">
        <?php foreach ($busiestHours as $h): ?>
          <?php $isPeak = $h['hour'] === $peak['hour']; ?>
          <div class="flex-1 min-w-0 flex flex-col items-center gap-1">
            <div class="w-full rounded-t"
                 style="height:<?= $maxHour > 0 ? max(3, (int) round($h['count'] / $maxHour * 48)) : 3 ?>px;
                        background:var(--accent);
                        opacity:<?= $isPeak ? '1' : '.32' ?>"
                 title="<?= e(fa_num($h['hour']) . ' — ' . fa_num($h['count']) . ' نوبت') ?>"></div>
            <span class="text-[11px] tabular-nums <?= $isPeak ? 'text-ink-700 font-bold' : 'text-ink-400' ?>">
              <?= e(fa_num($h['hour'])) ?>
            </span>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <?php if ($sleeping !== []): ?>
    <section class="glass rounded-2xl p-4">
      <h2 class="card-title mb-1">مشتری‌هایی که خبری ازشان نیست</h2>
      <!--
        «خوابیده» یعنی بیش از دو برابرِ فاصلهٔ معمولِ *خودش* نیامده، نه
        یک عدد ثابت برای همه. کسی که ماهی یک بار می‌آمد و دو ماه
        نیامده، جای نگرانی دارد؛ کسی که سالی دو بار می‌آید، نه.
      -->
      <p class="text-[12px] text-ink-400 mb-3 leading-relaxed">
        این‌ها مشتری‌های همیشگی بوده‌اند و حالا بیش از دو برابر همیشه غیبشان زده.
      </p>
      <ul class="space-y-1.5">
        <?php foreach ($sleeping as $c): ?>
          <li>
            <a href="<?= e(url('panel/customers/' . $c['id'])) ?>"
               class="tap flex items-center gap-2 rounded-xl px-2 py-2 -mx-2 hover:bg-ink-50">
              <span class="flex-1 min-w-0">
                <span class="block text-[13px] font-semibold text-ink-800 truncate">
                  <?= e($c['name'] ?? 'بی‌نام') ?>
                </span>
                <span class="block text-[12px] text-ink-400">
                  <?= e(fa_num((int) $c['visit_count'])) ?> بار آمده ·
                  <?= e(fa_num((int) $c['days_away'])) ?> روز است نیامده
                </span>
              </span>
              <?php if ($c['phone'] !== null): ?>
                <span class="w-9 h-9 shrink-0 rounded-xl grid place-items-center text-accent"
                      style="background:var(--accent-soft)" aria-hidden="true"><?= icon('phone', 'w-4 h-4') ?></span>
              <?php endif; ?>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    </section>
  <?php endif; ?>
</div>
