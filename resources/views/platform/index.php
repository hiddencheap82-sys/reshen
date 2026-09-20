<?php /** @var array $salons @var array $metrics */ ?>
<h1 class="text-lg font-bold text-slate-800 mb-5">پنل پلتفرم</h1>

<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
  <div class="bg-white rounded-2xl border border-slate-100 p-4 text-center">
    <div class="text-2xl font-extrabold text-brand-700"><?= fa_num($metrics['active_salons']) ?></div>
    <div class="text-[11px] text-slate-400 mt-1">سالن فعال</div>
  </div>
  <div class="bg-white rounded-2xl border border-slate-100 p-4 text-center">
    <div class="text-2xl font-extrabold text-slate-800"><?= fa_num($metrics['completed_this_week']) ?></div>
    <div class="text-[11px] text-slate-400 mt-1">نوبت به‌سرانجام‌رسیدهٔ هفته</div>
  </div>
  <div class="bg-white rounded-2xl border border-slate-100 p-4 text-center">
    <div class="text-2xl font-extrabold <?= $metrics['mae_minutes'] !== null && $metrics['mae_minutes'] > 12 ? 'text-red-500' : 'text-emerald-600' ?>">
      <?= $metrics['mae_minutes'] !== null ? fa_num($metrics['mae_minutes']) : '—' ?>
    </div>
    <div class="text-[11px] text-slate-400 mt-1">MAE (دقیقه)</div>
  </div>
  <div class="bg-white rounded-2xl border border-slate-100 p-4 text-center">
    <div class="text-2xl font-extrabold <?= $metrics['end_registration_rate'] !== null && $metrics['end_registration_rate'] < 70 ? 'text-red-500' : 'text-emerald-600' ?>">
      <?= $metrics['end_registration_rate'] !== null ? fa_num($metrics['end_registration_rate']) . '٪' : '—' ?>
    </div>
    <div class="text-[11px] text-slate-400 mt-1">نرخ ثبت پایان</div>
  </div>
</div>

<div class="bg-white rounded-2xl border border-slate-100 divide-y divide-slate-100">
  <?php foreach ($salons as $s): ?>
  <a href="<?= url('platform/' . $s['id']) ?>" class="flex items-center justify-between px-5 py-3.5 hover:bg-slate-50">
    <div>
      <div class="font-bold text-slate-800"><?= e($s['name']) ?></div>
      <div class="text-xs text-slate-400"><?= e($s['city'] ?? '—') ?> · <?= fa_num($s['staff_count']) ?> آرایشگر · <?= fa_num($s['completed_count']) ?> نوبت</div>
    </div>
    <div class="text-left">
      <span class="text-[11px] px-2 py-0.5 rounded-full bg-brand-50 text-brand-700"><?= e($s['plan_code']) ?></span>
      <div class="text-[11px] text-slate-400 mt-1"><?= jdate($s['created_at'], 'Y/m/d') ?></div>
    </div>
  </a>
  <?php endforeach; ?>
</div>
