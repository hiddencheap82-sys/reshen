<?php
/** @var array $salon @var array $appointment @var array $items @var ?array $display */
$statusLabels = [
    'pending' => 'در انتظار', 'confirmed' => 'تأییدشده', 'queued' => 'در صف',
    'in_chair' => 'روی صندلی', 'completed' => 'انجام‌شد', 'cancelled' => 'لغوشده', 'no_show' => 'غیبت',
];
$live = in_array($appointment['status'], ['confirmed','queued','in_chair'], true);
?>
<script>if (<?= $live ? 'true' : 'false' ?>) setTimeout(() => location.reload(), 20000);</script>

<h1 class="text-base font-bold text-slate-800 mb-1">نوبت من</h1>
<p class="text-sm text-slate-500 mb-5"><?= e($salon['name']) ?></p>

<div class="bg-slate-50 rounded-2xl p-5 text-center mb-5">
  <?php if ($appointment['status'] === 'in_chair'): ?>
    <div class="text-emerald-600 font-extrabold text-2xl mb-1">روی صندلی هستید 🎉</div>
  <?php elseif ($appointment['status'] === 'completed'): ?>
    <div class="text-slate-500 font-bold text-lg">نوبت شما انجام شد. ممنون از اعتمادتان 🙏</div>
  <?php elseif ($appointment['status'] === 'cancelled'): ?>
    <div class="text-red-500 font-bold text-lg">این نوبت لغو شده است.</div>
  <?php elseif ($appointment['status'] === 'no_show'): ?>
    <div class="text-slate-500 font-bold text-lg">این نوبت به‌عنوان غیبت ثبت شد.</div>
  <?php elseif ($display): ?>
    <div class="text-xs text-slate-400 mb-1">زمان تقریبی نوبت شما</div>
    <div class="text-2xl font-extrabold text-brand-700"><?= e($display['text']) ?></div>
    <?php if ($display['rough']): ?><div class="text-[11px] text-amber-600 mt-1">تخمین تقریبی</div><?php endif; ?>
  <?php else: ?>
    <div class="text-lg font-bold text-slate-600"><?= e($statusLabels[$appointment['status']] ?? $appointment['status']) ?></div>
  <?php endif; ?>
</div>

<div class="space-y-1.5 mb-5">
  <?php foreach ($items as $it): ?>
  <div class="flex items-center justify-between text-sm bg-white border border-slate-100 rounded-xl px-4 py-2.5">
    <span class="text-slate-600"><?= e($it['service_name']) ?></span>
    <span class="font-bold text-slate-800"><?= toman((int)$it['price']) ?></span>
  </div>
  <?php endforeach; ?>
</div>

<?php if ($appointment['scheduled_at']): ?>
<div class="text-xs text-slate-400 text-center mb-5">زمان رزروشده: <?= jdate($appointment['scheduled_at'], 'D j M، H:i') ?></div>
<?php endif; ?>

<?php if ($live): ?>
<form method="post" action="<?= url('q/' . $appointment['public_token'] . '/cancel') ?>" onsubmit="return confirm('نوبت لغو شود؟');">
  <?= csrf_field() ?>
  <button type="submit" class="w-full text-sm text-red-500 hover:text-red-600 border border-red-100 rounded-xl py-2.5">لغو نوبت</button>
</form>
<?php endif; ?>
