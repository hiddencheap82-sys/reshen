<?php
/**
 * صورتحساب‌های اشتراک.
 *
 * @var array $invoices
 * @var array $totals
 * @var string $status
 */
$active = 'invoices';
include __DIR__ . '/_nav.php';

$statusNames = [
    'pending' => ['در انتظار', 'text-ink-600', 'var(--fill-secondary)'],
    'paid' => ['پرداخت شد', 'text-emerald-700', '#ECFDF5'],
    'overdue' => ['معوق', 'text-red-700', '#FEF2F2'],
    'cancelled' => ['لغو شد', 'text-ink-400', 'var(--fill-secondary)'],
];
?>

<h1 class="page-title mb-4">صورتحساب‌ها</h1>

<nav class="flex flex-wrap gap-1.5 mb-4" aria-label="فیلتر وضعیت">
  <?php foreach (['' => 'همه'] + array_map(static fn ($v) => $v[0], $statusNames) as $key => $label): ?>
    <a href="<?= e(url('platform/invoices' . ($key === '' ? '' : '?status=' . $key))) ?>"
       class="tap inline-flex items-center h-11 px-3.5 rounded-xl text-[13px] font-semibold
              <?= $status === $key ? 'day-chip-on' : 'glass text-ink-700' ?>">
      <?= e($label) ?>
      <?php if ($key !== '' && isset($totals[$key])): ?>
        <span class="ms-1.5 text-[12px] opacity-70 tabular-nums"><?= e(fa_num($totals[$key]['count'])) ?></span>
      <?php endif; ?>
    </a>
  <?php endforeach; ?>
</nav>

<?php if ($invoices === []): ?>
  <div class="glass rounded-2xl py-12 text-center">
    <p class="text-sm text-ink-500">صورتحسابی با این وضعیت نیست.</p>
    <p class="text-[12px] text-ink-400 mt-1">از صفحهٔ هر سالن می‌توانید صورتحساب ماه را صادر کنید.</p>
  </div>
<?php else: ?>
  <div class="space-y-2">
    <?php foreach ($invoices as $inv): ?>
      <?php [$label, $tone, $bg] = $statusNames[$inv['status']] ?? ['—', 'text-ink-500', 'var(--fill-secondary)']; ?>
      <div class="glass rounded-2xl p-4">
        <div class="flex items-baseline gap-2 mb-2">
          <a href="<?= e(url('platform/' . $inv['salon_id'])) ?>"
             class="font-bold text-ink-900 flex-1 min-w-0 truncate"><?= e($inv['salon_name']) ?></a>
          <span class="text-[12px] rounded-full px-2 py-0.5 shrink-0 <?= $tone ?>"
                style="background:<?= $bg ?>"><?= e($label) ?></span>
        </div>

        <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1 text-[12px] text-ink-500 mb-3">
          <span class="tabular-nums"><?= e(jdate($inv['period_start'], 'Y/m/d')) ?>
            تا <?= e(jdate($inv['period_end'], 'Y/m/d')) ?></span>
          <span class="code"><?= e($inv['plan_code']) ?></span>
          <span class="ms-auto text-[15px] font-extrabold text-ink-800 tabular-nums">
            <?= e(App\Support\Money::fromRials((int) $inv['amount'])->formatToman()) ?>
          </span>
        </div>

        <?php if ($inv['status'] === 'pending' || $inv['status'] === 'overdue'): ?>
          <div class="flex gap-2">
            <form method="post" action="<?= e(url('platform/invoices/' . $inv['id'] . '/pay')) ?>" class="flex-1">
              <?= csrf_field() ?>
              <button class="btn-done w-full h-11 text-[13px]">پرداخت شد</button>
            </form>
            <form method="post" action="<?= e(url('platform/invoices/' . $inv['id'] . '/cancel')) ?>">
              <?= csrf_field() ?>
              <button class="tap h-11 px-4 rounded-xl text-[13px] font-bold text-ink-500 hover:bg-ink-100">لغو</button>
            </form>
          </div>
        <?php elseif ($inv['paid_at'] !== null): ?>
          <p class="text-[12px] text-ink-400 tabular-nums">
            پرداخت در <?= e(jdate($inv['paid_at'], 'Y/m/d')) ?>
          </p>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
