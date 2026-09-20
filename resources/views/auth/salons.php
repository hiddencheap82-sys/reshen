<?php /** @var array $memberships */ ?>
<h2 class="text-base font-bold text-slate-800 mb-1">انتخاب سالن</h2>
<p class="text-sm text-slate-500 mb-5">در چند سالن عضو هستید. کدام را باز کنیم؟</p>

<div class="space-y-2">
<?php foreach ($memberships as $m): ?>
  <a href="<?= url('salons/' . $m['salon_id'] . '/switch') ?>"
     class="flex items-center justify-between rounded-xl border border-slate-200 hover:border-brand-300 hover:bg-brand-50 px-4 py-3 transition">
    <span class="font-medium text-slate-800"><?= e($m['salon_name']) ?></span>
    <span class="text-xs text-slate-400"><?= e(['owner'=>'صاحب سالن','manager'=>'مدیر','staff'=>'آرایشگر','reception'=>'پذیرش'][$m['role']] ?? $m['role']) ?></span>
  </a>
<?php endforeach; ?>
</div>
<div class="text-center mt-5">
  <a href="<?= url('logout') ?>" class="text-xs text-slate-400 hover:text-slate-600">خروج از حساب</a>
</div>
