<?php
/**
 * مرجع زندهٔ سیستم دیزاین.
 *
 * چرا صفحه و نه سند: سندِ مارک‌داون از روز دوم با کد فرق می‌کند و
 * کسی نمی‌فهمد. این صفحه همان CSS و همان مؤلفه‌های واقعی برنامه را
 * رندر می‌کند، پس اگر چیزی عوض شود، اینجا هم عوض می‌شود — و اگر
 * چیزی بشکند، اینجا شکسته دیده می‌شود.
 *
 * پشت دسترسی مدیر کل است چون برای توسعه‌دهنده است، نه برای آرایشگر.
 */
$active = 'design';
include __DIR__ . '/_nav.php';

$tokens = [
    'سطح و متن' => [
        ['--bg', 'زمینهٔ صفحه'],
        ['--surface', 'سطح کارت'],
        ['--line', 'خط و حاشیه'],
        ['--text', 'متن اصلی'],
        ['--text-dim', 'متن کم‌رنگ'],
    ],
    'لهجه — صاحب سالن عوضش می‌کند' => [
        ['--accent', 'رنگ لهجه'],
        ['--accent-hi', 'حالت hover'],
        ['--accent-soft', 'زمینهٔ نرم'],
        ['--on-accent', 'متن روی لهجه'],
    ],
    'وضعیت — مستقل از لهجه' => [
        ['--ok-strong', 'موفقیت'],
        ['--ok-soft', 'زمینهٔ موفقیت'],
        ['--bad-strong', 'خطا'],
        ['--bad-soft', 'زمینهٔ خطا'],
        ['--warn-strong', 'هشدار'],
        ['--warn-soft', 'زمینهٔ هشدار'],
    ],
    'پرکنندهٔ خنثی' => [
        ['--fill-primary', 'پررنگ'],
        ['--fill-secondary', 'معمولی'],
        ['--fill-tertiary', 'کم‌رنگ'],
    ],
];
?>

<h1 class="page-title mb-1">سیستم دیزاین</h1>
<p class="text-[13px] text-ink-400 mb-5 leading-relaxed">
  این صفحه از همان CSS برنامه ساخته می‌شود. اگر چیزی اینجا خراب دیده شود، در خودِ برنامه هم خراب است.
  <br>کلید روشن/تیرهٔ بالا را بزنید تا هر دو حالت را ببینید.
</p>

<section class="glass rounded-2xl p-4 mb-4">
  <h2 class="card-title mb-3">توکن‌های رنگ</h2>
  <?php foreach ($tokens as $group => $rows): ?>
    <h3 class="text-[12px] font-bold text-ink-500 mt-4 mb-2 first:mt-0"><?= e($group) ?></h3>
    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
      <?php foreach ($rows as [$name, $label]): ?>
        <div class="flex items-center gap-2 min-w-0">
          <span class="w-9 h-9 shrink-0 rounded-lg" style="background:var(<?= $name ?>);border:1px solid var(--line)"></span>
          <span class="min-w-0">
            <span class="block text-[12px] font-semibold text-ink-700 truncate"><?= e($label) ?></span>
            <span class="block text-[11px] text-ink-400 code truncate" dir="ltr"><?= e($name) ?></span>
          </span>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>
</section>

<section class="glass rounded-2xl p-4 mb-4">
  <h2 class="card-title mb-1">دکمه‌ها</h2>
  <p class="text-[12px] text-ink-400 mb-3 leading-relaxed">
    کنشِ اصلیِ هر صفحه <span class="code">btn-accent metal</span> است. <span class="code">btn-ink</span>
    برای کنش فرعی است — جستجو، فیلتر، کپی. <span class="code">btn-done</span> فقط برای «تمام شد».
  </p>
  <div class="space-y-2">
    <button class="btn-accent metal w-full">کنش اصلی — btn-accent metal</button>
    <button class="btn-ink w-full">کنش فرعی — btn-ink</button>
    <button class="btn-done w-full">تمام شد — btn-done</button>
  </div>
</section>

<section class="glass rounded-2xl p-4 mb-4">
  <h2 class="card-title mb-1">ورودی</h2>
  <p class="text-[12px] text-ink-400 mb-3">
    یک کلاس: <span class="code">field</span>. ارتفاع ۴۴ پیکسل و حلقهٔ فوکوس را خودش تضمین می‌کند.
  </p>
  <div class="space-y-2">
    <div>
      <label for="ds-a" class="block text-[13px] font-semibold text-ink-800 mb-1.5">معمولی</label>
      <input id="ds-a" class="field" placeholder="روی من کلیک کنید تا حلقهٔ فوکوس را ببینید">
    </div>
    <div>
      <label for="ds-b" class="block text-[13px] font-semibold text-ink-800 mb-1.5">بزرگ — field-lg</label>
      <input id="ds-b" class="field field-lg" dir="ltr" placeholder="09123456789">
    </div>
  </div>
</section>

<section class="glass rounded-2xl p-4 mb-4">
  <h2 class="card-title mb-3">پیام و وضعیت</h2>
  <div class="space-y-2">
    <div class="flash flash-success" role="status">
      <?= icon('check', 'w-4 h-4 mt-0.5 shrink-0') ?>
      <span class="flex-1 text-[13px]">کار انجام شد — flash-success</span>
    </div>
    <div class="flash flash-error" role="status">
      <?= icon('alert', 'w-4 h-4 mt-0.5 shrink-0') ?>
      <span class="flex-1 text-[13px]">چیزی نشد — flash-error</span>
    </div>
  </div>
  <div class="flex flex-wrap gap-2 mt-3">
    <span class="text-[12px] rounded-full px-2.5 py-1 text-ok bg-ok-soft">موفق</span>
    <span class="text-[12px] rounded-full px-2.5 py-1 text-bad bg-bad-soft">ناموفق</span>
    <span class="text-[12px] rounded-full px-2.5 py-1 text-warn bg-warn-soft">هشدار</span>
    <span class="text-[12px] rounded-full px-2.5 py-1 text-ink-500" style="background:var(--fill-secondary)">خنثی</span>
  </div>
</section>

<section class="glass rounded-2xl p-4 mb-4">
  <h2 class="card-title mb-1">آواتار</h2>
  <p class="text-[12px] text-ink-400 mb-3">
    رنگ از نام درمی‌آید و همیشه همان می‌ماند. دوازده فام، همه بالای AA با متن سفید.
  </p>
  <div class="flex flex-wrap gap-2">
    <?php foreach (['مهدی احمدی','رضا صادقی','علی کریمی','کاوه شریفی','بهرام یزدی','امین تهرانی','حسین رضایی','محمد نوری'] as $n): ?>
      <?php $name = $n; $avatarColor = null; $avatarSize = 'w-10 h-10 text-[13px]';
            include BASE_PATH . '/resources/views/components/avatar.php'; ?>
    <?php endforeach; ?>
  </div>
</section>

<section class="glass rounded-2xl p-4 mb-4">
  <h2 class="card-title mb-1">آیکون‌ها</h2>
  <p class="text-[12px] text-ink-400 mb-3">
    خطی برای حالت عادی، پرشده برای تب فعال — <span class="code">nav_icon($name, $active)</span>.
  </p>
  <?php
  $sprite = (string) file_get_contents(BASE_PATH . '/resources/views/components/icons.svg');
  preg_match_all('/<symbol[^>]*id="i-([^"]+)"/', $sprite, $m);
  $all = $m[1];
  sort($all);
  ?>
  <div class="grid grid-cols-3 sm:grid-cols-5 gap-2">
    <?php foreach ($all as $name): ?>
      <div class="flex flex-col items-center gap-1 py-2 rounded-lg" style="background:var(--fill-tertiary)">
        <?= icon($name, 'w-5 h-5 text-ink-700') ?>
        <span class="text-[11px] text-ink-400 code truncate max-w-full px-1" dir="ltr"><?= e($name) ?></span>
      </div>
    <?php endforeach; ?>
  </div>
  <p class="text-[12px] text-ink-400 mt-3">
    جمعاً <?= e(fa_num(count($all))) ?> آیکون.
  </p>
</section>

<section class="glass rounded-2xl p-4">
  <h2 class="card-title mb-3">قواعدی که تست دارند</h2>
  <ul class="space-y-2 text-[13px] text-ink-600">
    <?php foreach ([
        ['هیچ متنی زیر WCAG AA نیست', 'ColorContrastTest'],
        ['ink-300 هیچ‌وقت متن نیست — رنگ جداکننده است', 'ColorContrastTest'],
        ['هر آیکونی که ویو صدا می‌زند در سپرایت هست', 'IconSpriteTest'],
        ['CSS ساخته‌شده با کلاس‌های ویوها جور است', 'StylesheetTest'],
        ['هیچ لایه‌ای پیام لحظه‌ای را بی‌صدا نمی‌بلعد', 'FlashMessageTest'],
        ['نشانهٔ تاریخِ ناشناخته روی صفحه نمی‌ماند', 'JalaliFormatTokensTest'],
    ] as [$rule, $test]): ?>
      <li class="flex items-start gap-2">
        <span class="text-ok shrink-0 mt-0.5"><?= icon('check', 'w-4 h-4') ?></span>
        <span class="flex-1 min-w-0">
          <?= e($rule) ?>
          <span class="block text-[11px] text-ink-400 code" dir="ltr"><?= e($test) ?></span>
        </span>
      </li>
    <?php endforeach; ?>
  </ul>
</section>
