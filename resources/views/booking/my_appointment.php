<?php
/**
 * کارت نوبتِ مشتری — صفحه‌ای که بعد از رزرو بارها باز می‌شود.
 *
 * پیش‌تر این صفحه از سیستم دیزاین جا مانده بود: جعبهٔ خاکستریِ ساده،
 * حاشیه‌های رنگِ ثابت، و شکلک به‌جای آیکون. بقیهٔ مسیر رزرو شیشه‌ای و
 * یک‌دست بود و این یکی — که مشتری بیش از همه می‌بیندش — نه.
 *
 * ساختار: یک کارتِ اصلی که «کِی» را می‌گوید (یا وضعیت را، اگر نوبت
 * تمام شده)، بعد جزئیات، بعد قاعدهٔ سر وقت آمدن، و در آخر لغو — دور
 * از دست، چون کنشی است که برگشت ندارد.
 *
 * @var array  $salon
 * @var array  $appointment
 * @var array  $items
 * @var ?array $display
 */
$live = in_array($appointment['status'], ['confirmed', 'queued', 'in_chair'], true);
$total = array_sum(array_map(static fn ($it) => (int) $it['price'], $items));
?>
<?php if ($live): ?>
  <!-- تا وقتی نوبت زنده است، هر ۲۰ ثانیه تازه شود تا تخمین جلو برود. -->
  <script>setTimeout(function () { location.reload(); }, 20000);</script>
<?php endif; ?>

<h1 class="text-[15px] font-extrabold text-ink-900 mb-0.5">نوبت من</h1>
<p class="text-[13px] text-ink-500 mb-4"><?= e($salon['name']) ?></p>

<?php
/*
 * کارت اصلی. هر وضعیت یک آیکون، یک رنگِ معنایی و یک جمله دارد — رنگ
 * هیچ‌وقت تنها نیست.
 */
?>
<section class="glass rounded-2xl p-5 text-center mb-4 <?= $live ? 'hairline-accent' : '' ?>">
  <?php if ($appointment['status'] === 'in_chair'): ?>
    <span class="w-11 h-11 mx-auto mb-2 rounded-full grid place-items-center text-ok"
          style="background:var(--ok-soft)" aria-hidden="true"><?= icon('scissors', 'w-5 h-5') ?></span>
    <p class="text-xl font-extrabold text-ok">روی صندلی هستید</p>

  <?php elseif ($appointment['status'] === 'completed'): ?>
    <span class="w-11 h-11 mx-auto mb-2 rounded-full grid place-items-center text-ok"
          style="background:var(--ok-soft)" aria-hidden="true"><?= icon('check', 'w-5 h-5') ?></span>
    <p class="text-[16px] font-bold text-ink-800">نوبت شما انجام شد</p>
    <p class="text-[13px] text-ink-500 mt-1">ممنون که آمدید.</p>

  <?php elseif ($appointment['status'] === 'cancelled'): ?>
    <span class="w-11 h-11 mx-auto mb-2 rounded-full grid place-items-center text-bad"
          style="background:var(--bad-soft)" aria-hidden="true"><?= icon('x', 'w-5 h-5') ?></span>
    <p class="text-[16px] font-bold text-bad">این نوبت لغو شده است</p>

  <?php elseif ($appointment['status'] === 'no_show'): ?>
    <span class="w-11 h-11 mx-auto mb-2 rounded-full grid place-items-center text-ink-500"
          style="background:var(--fill-secondary)" aria-hidden="true"><?= icon('user-x', 'w-5 h-5') ?></span>
    <p class="text-[16px] font-bold text-ink-700">این نوبت غیبت ثبت شد</p>

  <?php elseif ($display): ?>
    <p class="text-[12px] text-ink-400 mb-1">زمان تقریبی نوبت شما</p>
    <p class="text-2xl font-extrabold text-accent"><?= e($display['text']) ?></p>
    <?php if ($display['rough']): ?>
      <p class="text-[12px] text-warn mt-1">تخمین تقریبی — صف امروز شلوغ است</p>
    <?php endif; ?>

  <?php elseif ($appointment['status'] === 'confirmed' && $appointment['scheduled_at']): ?>
    <?php
    /*
     * نوبتِ روزهای بعد: در صفِ امروز نیست، پس تخمینی هم ندارد — و درست
     * است که نداشته باشد. سرتیترش خودِ روز و ساعتِ رزرو است، نه کلمهٔ
     * «تأییدشده».
     */
    $at = new DateTimeImmutable((string) $appointment['scheduled_at']);
    ?>
    <p class="text-[12px] text-ink-400 mb-1">نوبت شما</p>
    <p class="text-2xl font-extrabold text-accent">
      <?= e(App\Support\JalaliCalendar::relativeDate($at)) ?>، ساعت <?= e(fa_time($at->format('H:i'))) ?>
    </p>

  <?php else: ?>
    <p class="text-[16px] font-bold text-ink-700">در صف</p>
  <?php endif; ?>
</section>

<!-- جزئیات: خدمت‌ها و جمع، در یک کارت با جداکنندهٔ مویی — نه کارت به ازای هر ردیف. -->
<section class="glass rounded-2xl overflow-hidden mb-4">
  <?php foreach ($items as $it): ?>
    <div class="flex items-center justify-between gap-3 px-4 py-3 border-b" style="border-color:var(--line)">
      <span class="text-[13px] text-ink-700 min-w-0 truncate"><?= e($it['service_name']) ?></span>
      <span class="text-[13px] font-bold text-ink-800 tabular-nums shrink-0"><?= e(toman((int) $it['price'])) ?></span>
    </div>
  <?php endforeach; ?>
  <?php if (count($items) > 1): ?>
    <div class="flex items-center justify-between gap-3 px-4 py-3 border-b" style="border-color:var(--line)">
      <span class="text-[13px] font-bold text-ink-800">جمع</span>
      <span class="text-[13px] font-extrabold text-ink-900 tabular-nums"><?= e(toman($total)) ?></span>
    </div>
  <?php endif; ?>
  <?php if ($appointment['scheduled_at']): ?>
    <div class="flex items-center justify-between gap-3 px-4 py-3">
      <span class="text-[12px] text-ink-400">زمان رزروشده</span>
      <span class="text-[12px] text-ink-600 tabular-nums"><?= e(jdate($appointment['scheduled_at'], 'D j M، H:i')) ?></span>
    </div>
  <?php endif; ?>
</section>

<?php if ($live && $appointment['kind'] === 'booked' && $appointment['status'] === 'confirmed'): ?>
  <?php
  /*
   * قاعدهٔ سر وقت آمدن — به خودِ مشتری.
   *
   * صف یک قاعده دارد (QueueOrderingService): رزروی تا ده دقیقه بعد از
   * ساعتش بر حضوری‌ها مقدم است، و بعد از آن به ترتیب رسیدن. تا حالا
   * این قاعده فقط در کد بود؛ مشتری‌ای که ربع ساعت دیر می‌رسید و پشت
   * حضوری‌ها می‌نشست، نمی‌فهمید چرا — و فکر می‌کرد سالن به نوبتش
   * احترام نگذاشته. گفتنش از پیش، هم انگیزهٔ سر وقت آمدن است، هم
   * جلوی دلخوری را می‌گیرد.
   */
  $graceMinutes = (int) App\Core\Config::get('reshen.queue.priority_window_minutes', 10);
  ?>
  <div class="flex items-start gap-2.5 rounded-2xl px-4 py-3 mb-4 text-[12px] leading-relaxed text-ink-600"
       style="background:var(--fill-secondary)">
    <?= icon('clock', 'w-4 h-4 shrink-0 mt-0.5 text-ink-400') ?>
    <span>
      سر وقت بیا. اگر بیش از <?= e(fa_num($graceMinutes)) ?> دقیقه دیر برسی، نوبتت به ترتیبِ
      رسیدن حساب می‌شود و ممکن است منتظر بمانی.
    </span>
  </div>
<?php endif; ?>

<?php if ($live): ?>
  <form method="post" action="<?= e(url('q/' . $appointment['public_token'] . '/cancel')) ?>"
        onsubmit="return confirm('نوبت لغو شود؟');">
    <?= csrf_field() ?>
    <!-- کنشِ خطرناک فقط متن است، با رنگ خطا — همان قاعدهٔ پنل. -->
    <button type="submit" class="tap w-full h-11 rounded-xl text-[13px] font-semibold text-bad">
      لغو نوبت
    </button>
  </form>
<?php endif; ?>

<!--
  راه رسیدن به «نوبت‌های من».

  بدون این، مشتری فقط همین یک نوبت را دارد و راهی نیست که بفهمد
  صفحه‌ای هم هست که همهٔ نوبت‌هایش را نشان می‌دهد.
-->
<a href="<?= e(url('me')) ?>"
   class="flex items-center justify-center gap-2 text-[12px] font-semibold text-ink-500 mt-3 py-3 tap">
  <?= icon('calendar', 'w-4 h-4') ?>
  همهٔ نوبت‌های من
</a>
