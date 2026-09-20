<?php /** @var ?array $staff */ ?>
<div class="max-w-lg">
<div class="flex items-center gap-3 mb-5">
  <a href="<?= url('panel/staff') ?>" class="text-slate-400">←</a>
  <h1 class="text-lg font-bold text-slate-800"><?= $staff ? 'ویرایش آرایشگر' : 'آرایشگر جدید' ?></h1>
</div>

<form method="post" action="<?= url($staff ? 'panel/staff/' . $staff['id'] : 'panel/staff') ?>" class="bg-white rounded-2xl border border-slate-100 p-5 space-y-4">
  <?= csrf_field() ?>
  <div>
    <label class="block text-sm text-slate-600 mb-1.5">نام</label>
    <input type="text" name="name" required value="<?= e($staff['name'] ?? '') ?>"
      class="w-full rounded-xl border border-slate-200 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-brand-500">
  </div>
  <?php if (!$staff): ?>
  <div>
    <label class="block text-sm text-slate-600 mb-1.5">شمارهٔ موبایل (برای ورود آرایشگر به پنل — اختیاری)</label>
    <input type="tel" name="phone" dir="ltr" placeholder="09123456789"
      class="w-full rounded-xl border border-slate-200 px-4 py-3 text-left focus:outline-none focus:ring-2 focus:ring-brand-500">
  </div>
  <?php endif; ?>
  <div class="grid grid-cols-2 gap-3">
    <div>
      <label class="block text-sm text-slate-600 mb-1.5">درصد پیش‌فرض تسویه</label>
      <input type="number" step="0.1" name="commission_percent" value="<?= e((string)($staff['commission_percent'] ?? '')) ?>" placeholder="۵۰"
        class="w-full rounded-xl border border-slate-200 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-brand-500">
    </div>
    <div>
      <label class="block text-sm text-slate-600 mb-1.5">رنگ نمایشی</label>
      <input type="color" name="color" value="<?= e($staff['color'] ?? '#2563eb') ?>"
        class="w-full h-[46px] rounded-xl border border-slate-200 px-2">
    </div>
  </div>
  <button type="submit" class="w-full bg-brand-600 hover:bg-brand-700 text-white font-bold rounded-xl py-3">ذخیره</button>
</form>

<?php if ($staff): ?>
<form method="post" action="<?= url('panel/staff/' . $staff['id'] . '/toggle') ?>" class="mt-3">
  <?= csrf_field() ?>
  <button type="submit" class="w-full text-sm text-slate-400 hover:text-slate-600 py-2">
    <?= $staff['is_active'] ? 'غیرفعال کردن' : 'فعال کردن' ?>
  </button>
</form>
<?php endif; ?>
</div>
