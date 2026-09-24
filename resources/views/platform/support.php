<?php
/**
 * تیکت‌های پشتیبانی، از دید پلتفرم.
 *
 * ترتیب عمدی: «منتظر ما» بالا. کاری که مانده مهم‌تر از کاری است که
 * تمام شده — و تیکتی که پایین فهرست گم شود، تیکتی است که جواب
 * نمی‌گیرد.
 *
 * @var array $tickets
 * @var array $counts
 * @var string $status
 */
$active = 'support';
include __DIR__ . '/_nav.php';

$badge = [
    'open' => ['منتظر ما', 'text-bad', 'var(--bad-soft)'],
    'answered' => ['منتظر سالن', 'text-warn', 'var(--warn-soft)'],
    'closed' => ['بسته', 'text-ink-500', 'var(--fill-secondary)'],
];

$filters = [
    '' => 'همه',
    'open' => 'منتظر ما',
    'answered' => 'منتظر سالن',
    'closed' => 'بسته',
];
?>

<div class="mb-4">
  <h1 class="page-title">پشتیبانی</h1>
  <p class="text-[12px] text-ink-400 mt-1 max-w-[52ch] leading-relaxed">
    سؤال‌هایی که از پنل سالن‌ها آمده. جوابتان همان‌جا در پنل خودشان دیده می‌شود.
  </p>
</div>

<div class="flex items-center gap-2 mb-4 overflow-x-auto no-scrollbar -mx-4 px-4">
  <?php foreach ($filters as $key => $label): ?>
    <?php $on = $status === $key; ?>
    <a href="<?= e(url('platform/support' . ($key === '' ? '' : '?status=' . $key))) ?>"
       class="tap shrink-0 h-11 px-3.5 inline-flex items-center gap-1.5 rounded-xl text-[12px] font-semibold
              <?= $on ? 'day-chip-on' : 'glass text-ink-700' ?>">
      <?= e($label) ?>
      <?php if ($key !== '' && ($counts[$key] ?? 0) > 0): ?>
        <span class="tabular-nums opacity-70"><?= e(fa_num($counts[$key])) ?></span>
      <?php endif; ?>
    </a>
  <?php endforeach; ?>
</div>

<?php if ($tickets === []): ?>
  <div class="glass rounded-2xl py-12 px-5 text-center">
    <?= icon('phone', 'w-9 h-9 mx-auto text-ink-400 mb-3') ?>
    <p class="text-sm font-bold text-ink-700 mb-1">تیکتی نیست</p>
    <p class="text-[12px] text-ink-400">هنوز کسی سؤالی نپرسیده.</p>
  </div>
<?php else: ?>
  <div class="glass rounded-2xl overflow-hidden">
    <?php foreach ($tickets as $t): ?>
      <?php [$label, $textClass, $bg] = $badge[$t['status']]; ?>
      <a href="<?= e(url('platform/support/' . $t['id'])) ?>"
         class="tap flex items-center gap-3 px-4 py-3.5 border-b last:border-b-0 hover:bg-ink-50"
         style="border-color:var(--line)">
        <span class="flex-1 min-w-0">
          <span class="block text-[13.5px] font-bold text-ink-900 truncate"><?= e($t['subject']) ?></span>
          <span class="block text-[12px] text-ink-400 truncate mt-0.5">
            <?= e($t['salon_name']) ?>
            · <?= e(fa_num((int) $t['message_count'])) ?> پیام
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
