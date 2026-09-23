<?php
/**
 * یک تیکت، از دید سالن.
 *
 * @var array $ticket
 * @var array $messages
 */
$badge = [
    'open' => ['منتظر جواب', 'text-warn', 'var(--warn-soft)'],
    'answered' => ['جواب آمده', 'text-accent', 'var(--accent-soft)'],
    'closed' => ['بسته', 'text-ink-500', 'var(--fill-secondary)'],
];
[$label, $textClass, $bg] = $badge[$ticket['status']];
?>

<div class="flex items-center gap-2 mb-5">
  <a href="<?= e(url('panel/support')) ?>"
     class="w-11 h-11 grid place-items-center rounded-xl text-ink-500 hover:bg-ink-100 tap shrink-0"
     aria-label="بازگشت"><?= icon('chevron-start', 'w-4 h-4') ?></a>
  <div class="min-w-0 flex-1">
    <h1 class="page-title truncate"><?= e($ticket['subject']) ?></h1>
    <p class="text-[12px] text-ink-400 mt-0.5 tabular-nums">
      <?= e(jdate((string) $ticket['created_at'], 'Y/m/d')) ?>
    </p>
  </div>
  <span class="shrink-0 text-[11px] font-bold px-2.5 py-1.5 rounded-lg <?= $textClass ?>"
        style="background:<?= $bg ?>"><?= e($label) ?></span>
</div>

<div class="max-w-2xl space-y-4">
  <?php echo App\Core\View::render('components.ticket-thread', [
      'messages' => $messages,
      'mySide' => 'salon',
  ]); ?>

  <form method="post" action="<?= e(url('panel/support/' . $ticket['id'] . '/reply')) ?>"
        class="glass rounded-2xl p-4 space-y-3">
    <?= csrf_field() ?>
    <label for="body" class="block text-[12px] font-bold text-ink-600">
      <?= $ticket['status'] === 'closed' ? 'باز کردن دوباره' : 'پیام تازه' ?>
    </label>
    <textarea name="body" id="body" rows="4" required
              placeholder="<?= $ticket['status'] === 'closed'
                  ? 'اگر هنوز مشکل دارید بنویسید — تیکت دوباره باز می‌شود.'
                  : 'پیامتان…' ?>"
              class="field"></textarea>
    <button type="submit" class="btn-accent metal w-full">ارسال</button>
  </form>
</div>
