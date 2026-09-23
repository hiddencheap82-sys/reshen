<?php
/**
 * پشتیبانی، از دید سالن.
 *
 * حالت خالی اینجا مهم‌تر از فهرست است: بیشتر وقت‌ها این صفحه خالی
 * است، و کاری که باید بکند این است که بگوید «می‌شود پرسید» — نه
 * اینکه یک جدول خالی نشان دهد.
 *
 * @var array $tickets
 */
$badge = [
    'open' => ['منتظر جواب', 'text-warn', 'var(--warn-soft)'],
    'answered' => ['جواب آمده', 'text-accent', 'var(--accent-soft)'],
    'closed' => ['بسته', 'text-ink-500', 'var(--fill-secondary)'],
];
?>

<div class="flex items-start justify-between gap-3 mb-5">
  <div>
    <h1 class="page-title">پشتیبانی</h1>
    <p class="text-[12px] text-ink-400 mt-1">هر سؤالی دربارهٔ برنامه دارید، همین‌جا بپرسید.</p>
  </div>
  <a href="<?= e(url('panel/support/new')) ?>" class="btn-accent metal h-11 px-4 text-[13px] shrink-0">
    <?= icon('plus', 'w-4 h-4') ?>
    سؤال تازه
  </a>
</div>

<?php if ($tickets === []): ?>
  <div class="glass rounded-2xl py-12 px-5 text-center">
    <?= icon('phone', 'w-10 h-10 mx-auto text-ink-400 mb-3') ?>
    <p class="text-sm font-bold text-ink-700 mb-1">هنوز سؤالی نپرسیده‌اید</p>
    <p class="text-[12px] text-ink-400 leading-relaxed max-w-[38ch] mx-auto mb-4">
      چیزی کار نمی‌کند؟ چیزی را نمی‌فهمید؟ بپرسید — جواب را همین‌جا در
      پنل خودتان می‌بینید.
    </p>
    <a href="<?= e(url('panel/support/new')) ?>" class="btn-accent metal px-5">اولین سؤال</a>
  </div>
<?php else: ?>
  <div class="glass rounded-2xl overflow-hidden">
    <?php foreach ($tickets as $t): ?>
      <?php [$label, $textClass, $bg] = $badge[$t['status']]; ?>
      <a href="<?= e(url('panel/support/' . $t['id'])) ?>"
         class="tap flex items-center gap-3 px-4 py-3.5 border-b last:border-b-0 hover:bg-ink-50"
         style="border-color:var(--line)">
        <span class="flex-1 min-w-0">
          <span class="block text-[13.5px] font-bold text-ink-900 truncate"><?= e($t['subject']) ?></span>
          <span class="block text-[12px] text-ink-400 mt-0.5 tabular-nums">
            <?= e(fa_num((int) $t['message_count'])) ?> پیام
            <?php if ($t['last_message_at'] !== null): ?>
              · <?= e(jdate((string) $t['last_message_at'], 'Y/m/d H:i')) ?>
            <?php endif; ?>
          </span>
        </span>
        <span class="shrink-0 text-[11px] font-bold px-2 py-1 rounded-lg <?= $textClass ?>"
              style="background:<?= $bg ?>"><?= e($label) ?></span>
        <?= icon('chevron-end', 'w-4 h-4 text-ink-400 shrink-0') ?>
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
