<?php
/** @var array $salon @var array $appointment @var array $items @var ?array $display */
$statusLabels = [
    'pending' => 'در انتظار', 'confirmed' => 'تأییدشده', 'queued' => 'در صف',
    'in_chair' => 'روی صندلی', 'completed' => 'انجام‌شد', 'cancelled' => 'لغوشده', 'no_show' => 'غیبت',
];
$live = in_array($appointment['status'], ['confirmed','queued','in_chair'], true);
?>
<script>if (<?= $live ? 'true' : 'false' ?>) setTimeout(() => location.reload(), 20000);</script>

<h1 class="text-[15px] font-extrabold text-ink-900 mb-1">نوبت من</h1>
<p class="text-sm text-ink-500 mb-5"><?= e($salon['name']) ?></p>

<div class="bg-ink-50 rounded-2xl p-5 text-center mb-5">
  <?php if ($appointment['status'] === 'in_chair'): ?>
    <div class="text-ok font-extrabold text-2xl mb-1">روی صندلی هستید 🎉</div>
  <?php elseif ($appointment['status'] === 'completed'): ?>
    <div class="text-ink-500 font-bold text-lg">نوبت شما انجام شد. ممنون از اعتمادتان 🙏</div>
  <?php elseif ($appointment['status'] === 'cancelled'): ?>
    <div class="text-bad font-bold text-lg">این نوبت لغو شده است.</div>
  <?php elseif ($appointment['status'] === 'no_show'): ?>
    <div class="text-ink-500 font-bold text-lg">این نوبت به‌عنوان غیبت ثبت شد.</div>
  <?php elseif ($display): ?>
    <div class="text-xs text-ink-400 mb-1">زمان تقریبی نوبت شما</div>
    <div class="text-2xl font-extrabold text-accent"><?= e($display['text']) ?></div>
    <?php if ($display['rough']): ?><div class="text-[12px] text-warn mt-1">تخمین تقریبی</div><?php endif; ?>
  <?php elseif ($appointment['status'] === 'confirmed' && $appointment['scheduled_at']): ?>
    <?php
    /*
     * نوبتِ روزهای بعد.
     *
     * در صفِ امروز نیست، پس تخمینی هم ندارد — و درست است که نداشته
     * باشد: تخمینِ صف برای امروز معنی دارد، نه برای سه‌شنبهٔ بعد. سرتیترش
     * خودِ روز و ساعتِ رزرو است، نه کلمهٔ «تأییدشده».
     */
    $at = new DateTimeImmutable((string) $appointment['scheduled_at']);
    ?>
    <div class="text-xs text-ink-400 mb-1">نوبت شما</div>
    <div class="text-2xl font-extrabold text-accent">
      <?= e(App\Support\JalaliCalendar::relativeDate($at)) ?>، ساعت <?= e(fa_time($at->format('H:i'))) ?>
    </div>
  <?php else: ?>
    <div class="text-lg font-bold text-ink-600"><?= e($statusLabels[$appointment['status']] ?? $appointment['status']) ?></div>
  <?php endif; ?>
</div>

<div class="space-y-1.5 mb-5">
  <?php foreach ($items as $it): ?>
  <div class="flex items-center justify-between text-sm bg-white border border-ink-100 rounded-xl px-4 py-2.5">
    <span class="text-ink-600"><?= e($it['service_name']) ?></span>
    <span class="font-bold text-ink-800"><?= toman((int)$it['price']) ?></span>
  </div>
  <?php endforeach; ?>
</div>

<?php if ($appointment['scheduled_at']): ?>
<div class="text-xs text-ink-400 text-center mb-3">زمان رزروشده: <?= jdate($appointment['scheduled_at'], 'D j M، H:i') ?></div>
<?php endif; ?>

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
  <div class="flex items-start gap-2.5 rounded-xl px-3.5 py-3 mb-5 text-[12px] leading-relaxed text-ink-600"
       style="background:var(--fill-secondary)">
    <?= icon('clock', 'w-4 h-4 shrink-0 mt-0.5 text-ink-400') ?>
    <span>
      سر وقت بیا. اگر بیش از <?= e(fa_num($graceMinutes)) ?> دقیقه دیر برسی، نوبتت به ترتیبِ
      رسیدن حساب می‌شود و ممکن است منتظر بمانی.
    </span>
  </div>
<?php endif; ?>

<?php if ($live): ?>
<form method="post" action="<?= url('q/' . $appointment['public_token'] . '/cancel') ?>" onsubmit="return confirm('نوبت لغو شود؟');">
  <?= csrf_field() ?>
  <button type="submit" class="w-full text-sm text-bad hover:text-bad border border-red-100 rounded-xl py-2.5">لغو نوبت</button>
</form>
<?php endif; ?>

<!--
  راه رسیدن به «نوبت‌های من».

  بدون این، مشتری فقط همین یک نوبت را دارد و راهی نیست که بفهمد
  صفحه‌ای هم هست که همهٔ نوبت‌هایش را نشان می‌دهد.
-->
<a href="<?= e(url('me')) ?>"
   class="flex items-center justify-center gap-2 text-[12px] font-semibold text-ink-500 mt-5 py-3 tap">
  <?= icon('calendar', 'w-4 h-4') ?>
  همهٔ نوبت‌های من
</a>
