<?php
/**
 * یک تیکت، از دید پشتیبانی.
 *
 * کنار گفتگو، ایرادهای همان سالن هم نشان داده می‌شود. چرا: نیمی از
 * سؤال‌های پشتیبانی جوابشان همان چیزی است که صفحهٔ سلامت از قبل
 * می‌داند — «سانس‌هام نشون داده نمیشه» معمولاً یعنی آرایشگر فعالی
 * ندارد. بدون این، پشتیبان باید سه پیام رد و بدل کند تا به همان
 * برسد.
 *
 * @var array $ticket
 * @var array $messages
 * @var array $issues
 */
$active = 'support';
include __DIR__ . '/_nav.php';

$badge = [
    'open' => ['منتظر ما', 'text-bad', 'var(--bad-soft)'],
    'answered' => ['منتظر سالن', 'text-warn', 'var(--warn-soft)'],
    'closed' => ['بسته', 'text-ink-500', 'var(--fill-secondary)'],
];
[$label, $textClass, $bg] = $badge[$ticket['status']];
?>

<div class="flex items-center gap-2 mb-5">
  <a href="<?= e(url('platform/support')) ?>"
     class="w-11 h-11 grid place-items-center rounded-xl text-ink-500 hover:bg-ink-100 tap shrink-0"
     aria-label="بازگشت به تیکت‌ها"><?= icon('chevron-start', 'w-4 h-4') ?></a>
  <div class="min-w-0 flex-1">
    <h1 class="page-title truncate"><?= e($ticket['subject']) ?></h1>
    <p class="text-[12px] text-ink-400 mt-0.5 truncate">
      <a href="<?= e(url('platform/' . $ticket['salon_id'])) ?>"
         class="text-accent font-semibold"><?= e($ticket['salon_name']) ?></a>
      <?php if (($ticket['opener_phone'] ?? '') !== ''): ?>
        · <span dir="ltr" class="tabular-nums"><?= e($ticket['opener_phone']) ?></span>
      <?php endif; ?>
    </p>
  </div>
  <span class="shrink-0 text-[11px] font-bold px-2.5 py-1.5 rounded-lg <?= $textClass ?>"
        style="background:<?= $bg ?>"><?= e($label) ?></span>
</div>

<div class="grid lg:grid-cols-[1.4fr_1fr] gap-4 items-start">
  <div class="space-y-4">
    <?php echo App\Core\View::render('components.ticket-thread', [
        'messages' => $messages,
        'mySide' => 'platform',
    ]); ?>

    <form method="post" action="<?= e(url('platform/support/' . $ticket['id'] . '/reply')) ?>"
          class="glass rounded-2xl p-4 space-y-3">
      <?= csrf_field() ?>
      <label for="body" class="block text-[12px] font-bold text-ink-600">جواب</label>
      <textarea name="body" id="body" rows="4" required
                placeholder="جوابتان را اینجا بنویسید…" class="field"></textarea>
      <div class="flex gap-2">
        <button type="submit" class="btn-accent metal flex-1">ارسال جواب</button>
      </div>
    </form>

    <?php if ($ticket['status'] !== 'closed'): ?>
      <form method="post" action="<?= e(url('platform/support/' . $ticket['id'] . '/close')) ?>">
        <?= csrf_field() ?>
        <button type="submit" class="btn-ink w-full">بستن تیکت</button>
      </form>
    <?php endif; ?>
  </div>

  <aside class="glass rounded-2xl p-4">
    <h2 class="card-title mb-3">وضعیت این سالن</h2>

    <?php if ($issues === []): ?>
      <p class="text-[12px] text-ink-400 leading-relaxed">
        هیچ ایراد شناخته‌شده‌ای ندارد — آرایشگر، خدمت و ساعت کاری سرجایشان‌اند.
      </p>
    <?php else: ?>
      <p class="text-[12px] text-ink-400 mb-3 leading-relaxed">
        شاید جواب سؤالش همین‌ها باشد.
      </p>
      <ul class="space-y-2.5">
        <?php foreach ($issues as $issue): ?>
          <li class="flex items-start gap-2.5">
            <span class="w-2 h-2 mt-1.5 shrink-0 rounded-full"
                  style="background:<?= $issue['level'] === 'blocking' ? 'var(--bad-strong)' : 'var(--warn-strong)' ?>"
                  aria-hidden="true"></span>
            <div class="min-w-0">
              <p class="text-[12.5px] font-bold text-ink-800"><?= e($issue['title']) ?></p>
              <p class="text-[12px] text-ink-500 mt-0.5 leading-relaxed"><?= e($issue['fix']) ?></p>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <form method="post" action="<?= e(url('platform/' . $ticket['salon_id'] . '/impersonate')) ?>"
          class="mt-4 pt-4 border-t" style="border-color:var(--line)">
      <?= csrf_field() ?>
      <button type="submit" class="btn-ink w-full">ورود به پنل این سالن</button>
      <p class="text-[11px] text-ink-400 mt-2 leading-relaxed">
        در گزارش فعالیت ثبت می‌شود.
      </p>
    </form>
  </aside>
</div>
