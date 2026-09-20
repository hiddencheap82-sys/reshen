<?php
/** @var string $date @var array $totals @var array $breakdown @var array $byStaff @var array $rescued */
$methodLabels = ['cash'=>'نقدی','card_to_card'=>'کارت‌به‌کارت','pos'=>'کارتخوان','online'=>'آنلاین'];
?>
<div class="flex items-center justify-between mb-5">
  <h1 class="text-lg font-bold text-ink-800">گزارش روزانه</h1>
  <a href="<?= url('panel/reports/monthly') ?>" class="text-xs text-gold-700 hover:underline">گزارش ماهانه ←</a>
</div>

<form method="get" class="mb-5">
  <input type="date" name="date" value="<?= e($date) ?>" onchange="this.form.submit()" class="rounded-xl border border-ink-200 px-3 py-2 text-sm">
</form>

<div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
  <div class="bg-white rounded-2xl border border-ink-100 p-4 text-center">
    <div class="text-2xl font-extrabold text-gold-700"><?= toman((int)$totals['total']) ?></div>
    <div class="text-[11px] text-ink-400 mt-1">فروش کل</div>
  </div>
  <div class="bg-white rounded-2xl border border-ink-100 p-4 text-center">
    <div class="text-2xl font-extrabold text-ink-800"><?= fa_num($totals['count']) ?></div>
    <div class="text-[11px] text-ink-400 mt-1">تعداد نوبت</div>
  </div>
  <div class="bg-white rounded-2xl border border-ink-100 p-4 text-center">
    <div class="text-2xl font-extrabold text-ink-800"><?= toman((int)$totals['tips']) ?></div>
    <div class="text-[11px] text-ink-400 mt-1">انعام</div>
  </div>
  <div class="bg-emerald-50 rounded-2xl border border-emerald-100 p-4 text-center">
    <div class="text-2xl font-extrabold text-emerald-700"><?= fa_num($rescued['count']) ?></div>
    <div class="text-[11px] text-emerald-600 mt-1">نجات‌یافته با یادآور</div>
  </div>
</div>

<div class="grid md:grid-cols-2 gap-5">
  <div class="bg-white rounded-2xl border border-ink-100 p-5">
    <h2 class="text-sm font-bold text-ink-700 mb-3">به تفکیک روش پرداخت</h2>
    <?php foreach ($breakdown as $b): ?>
    <div class="flex items-center justify-between text-sm py-1.5">
      <span class="text-ink-600"><?= e($methodLabels[$b['method']] ?? $b['method']) ?></span>
      <span class="font-bold text-ink-800"><?= toman((int)$b['total']) ?></span>
    </div>
    <?php endforeach; ?>
    <?php if (empty($breakdown)): ?><p class="text-xs text-ink-400">پرداختی ثبت نشده.</p><?php endif; ?>
  </div>
  <div class="bg-white rounded-2xl border border-ink-100 p-5">
    <h2 class="text-sm font-bold text-ink-700 mb-3">به تفکیک آرایشگر</h2>
    <?php foreach ($byStaff as $s): ?>
    <div class="flex items-center justify-between text-sm py-1.5">
      <span class="text-ink-600"><?= e($s['name']) ?> <span class="text-[11px] text-ink-400">(<?= fa_num($s['count']) ?>)</span></span>
      <span class="font-bold text-ink-800"><?= toman((int)$s['total']) ?></span>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<?php if ($rescued['count'] > 0): ?>
<div class="bg-emerald-50 border border-emerald-100 rounded-2xl p-5 mt-5 text-sm text-emerald-800">
  <?= fa_num($rescued['count']) ?> نوبتی که با پیامک یادآور نجات پیدا کرد ≈ <?= toman((int)$rescued['value']) ?>
</div>
<?php endif; ?>
