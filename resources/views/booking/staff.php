<?php
/**
 * گام ۳ — آرایشگر.
 *
 * فهرست فقط آرایشگرهایی را دارد که همان سانس آزادند؛ فیلتر در کنترلر
 * انجام شده، چون نمایش دادنِ آرایشگری که بعد رد می‌شود یعنی مشتری را
 * دو گام جلو ببری و برگردانی.
 *
 * @var array $salon
 * @var array $staff
 * @var ?string $slotLabel
 */
?>


<?php if ($slotLabel !== null): ?>
  <a href="<?= e(url('s/' . $salon['slug'])) ?>"
     class="rise glass rounded-2xl px-4 py-3 mb-5 flex items-center gap-3 tap">
    <?= icon('calendar', 'w-4 h-4 text-ink-400 shrink-0') ?>
    <span class="flex-1 min-w-0">
      <span class="block text-[12px] text-ink-500">وقت انتخابی</span>
      <span class="block text-[13px] font-bold text-ink-900 truncate"><?= e($slotLabel) ?></span>
    </span>
    <span class="text-[12px] font-semibold text-accent shrink-0">تغییر</span>
  </a>
<?php endif; ?>

<div class="rise rise-1">
  <h1 class="text-[15px] font-extrabold text-ink-900 mb-1">کدام آرایشگر؟</h1>
  <p class="text-[13px] text-ink-500 mb-4">
    <?= e(fa_num(count($staff))) ?> نفر در این ساعت آزادند. اگر فرقی نمی‌کند، همان گزینهٔ اول را بزن.
  </p>
</div>

<form method="post" action="<?= e(url('s/' . $salon['slug'] . '/staff')) ?>">
  <?= csrf_field() ?>

  <fieldset class="space-y-2.5">
    <legend class="sr-only">انتخاب آرایشگر</legend>

    <label class="pick rise rise-2 block relative tap">
      <input type="radio" name="staff_id" value="" checked class="sr-only">
      <span class="pick-card glass flex items-center gap-3.5 rounded-2xl px-4 py-3.5
                   transition-all duration-200 ease-out-soft hover:shadow-lift">
        <span class="pick-box w-6 h-6 shrink-0 rounded-full border-2 border-ink-300 grid place-items-center
                     transition-colors duration-200" aria-hidden="true">
          <?= icon('check', 'pick-tick w-3.5 h-3.5 opacity-0 transition-opacity duration-200') ?>
        </span>
        <span class="flex-1 min-w-0">
          <span class="block text-[14px] font-bold text-ink-900">فرقی نمی‌کند</span>
          <span class="block text-[12px] text-ink-500 mt-0.5">هر کسی که آزاد باشد</span>
        </span>
      </span>
    </label>

    <?php foreach ($staff as $i => $st): ?>
      <label class="pick rise rise-<?= min($i + 3, 5) ?> block relative tap">
        <input type="radio" name="staff_id" value="<?= (int) $st['id'] ?>" class="sr-only">
        <span class="pick-card glass flex items-center gap-3.5 rounded-2xl px-4 py-3.5
                     transition-all duration-200 ease-out-soft hover:shadow-lift">
          <span class="pick-box w-6 h-6 shrink-0 rounded-full border-2 border-ink-300 grid place-items-center
                       transition-colors duration-200" aria-hidden="true">
            <?= icon('check', 'pick-tick w-3.5 h-3.5 opacity-0 transition-opacity duration-200') ?>
          </span>
          <span class="w-9 h-9 shrink-0 rounded-full grid place-items-center text-white text-[13px] font-bold"
                style="background:<?= e($st['color']) ?>" aria-hidden="true">
            <?= e(mb_substr($st['name'], 0, 1)) ?>
          </span>
          <span class="flex-1 min-w-0">
            <span class="block text-[14px] font-bold text-ink-900 truncate"><?= e($st['name']) ?></span>
          </span>
        </span>
      </label>
    <?php endforeach; ?>
  </fieldset>

<?php
  echo App\Core\View::render('components.sticky-action', [
      'label' => 'ادامه',
      'hint' => 'یا بگذار هر آرایشگری که زودتر آزاد شد',
  ]);
  ?>
</form>
