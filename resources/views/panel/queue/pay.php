<?php
/** @var array $appointment
 * @var array $items
 * @var ?array $customer
 * @var int $amount
 */
use App\Support\Money;
?>
<div class="max-w-md mx-auto">
  <h1 class="text-lg font-bold text-ink-800 mb-1">تسویه</h1>
  <p class="text-sm text-ink-500 mb-5"><?= e($customer['name'] ?? 'مشتری') ?></p>

  <div class="bg-white rounded-2xl border border-ink-100 p-5 mb-4">
    <?php foreach ($items as $it): ?>
    <div class="flex items-center justify-between text-sm py-1.5">
      <span class="text-ink-600"><?= e($it['service_name']) ?></span>
      <span class="font-bold text-ink-800"><?= toman((int)$it['price']) ?></span>
    </div>
    <?php endforeach; ?>
  </div>

  <form method="post" action="<?= url('panel/pay/' . $appointment['id']) ?>" class="bg-white rounded-2xl border border-ink-100 p-5 space-y-4">
    <?= csrf_field() ?>
    <div>
      <label class="block text-sm text-ink-600 mb-1.5">مبلغ کل (تومان)</label>
      <input type="number" name="amount_toman" value="<?= (int) Money::fromRials($amount)->toToman() ?>"
        class="w-full rounded-xl border border-ink-200 px-4 py-3 text-lg font-bold focus:outline-none focus:ring-2 focus:ring-brand-500">
    </div>
    <div>
      <label class="block text-sm text-ink-600 mb-1.5">انعام (اختیاری)</label>
      <input type="number" name="tip_toman" value="0" class="w-full rounded-xl border border-ink-200 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-brand-500">
    </div>
    <div>
      <label class="block text-sm text-ink-600 mb-2">روش پرداخت</label>
      <div class="grid grid-cols-2 gap-2">
        <?php $methods = ['cash'=>'نقدی','card_to_card'=>'کارت‌به‌کارت','pos'=>'کارتخوان','online'=>'آنلاین']; ?>
        <?php foreach ($methods as $val=>$label): ?>
        <label class="flex items-center justify-center gap-1.5 text-sm border border-ink-200 rounded-xl py-2.5 cursor-pointer has-[:checked]:bg-gold-50 has-[:checked]:border-gold-600 has-[:checked]:text-gold-700">
          <input type="radio" name="method" value="<?= $val ?>" class="hidden" <?= $val==='cash'?'checked':'' ?>><?= $label ?>
        </label>
        <?php endforeach; ?>
      </div>
    </div>
    <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-lg rounded-xl py-4">ثبت پرداخت</button>
  </form>
</div>
