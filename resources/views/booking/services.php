<?php
/**
 * گام ۲ — خدمت. روز و سانس در گام قبل قفل شده‌اند.
 *
 * @var array $salon
 * @var array $services
 * @var ?string $slotLabel
 * @var int[] $selected
 */
?>


<!--
  وقتِ انتخاب‌شده بالای صفحه می‌ماند.

  مشتری تازه یک تصمیم گرفته و حالا در صفحهٔ دیگری است؛ بدون این، یادش
  می‌رود چه ساعتی را گرفته و برای اطمینان برمی‌گردد عقب.
-->
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
  <h1 class="text-[15px] font-extrabold text-ink-900 mb-1">چه خدمتی می‌خواهی؟</h1>
  <p class="text-[13px] text-ink-500 mb-4">می‌توانی چند مورد را با هم انتخاب کنی.</p>
</div>

<?php if (empty($services)): ?>
  <div class="glass rounded-2xl py-12 px-5 text-center">
    <?= icon('scissors', 'w-10 h-10 mx-auto text-ink-300 mb-3') ?>
    <p class="text-sm font-semibold text-ink-600">هنوز خدمتی تعریف نشده</p>
    <p class="text-[12px] text-ink-400 mt-1">با خود آرایشگاه تماس بگیرید.</p>
  </div>

<?php else: ?>
  <form method="post" action="<?= e(url('s/' . $salon['slug'] . '/services')) ?>" id="svc-form">
    <?= csrf_field() ?>

    <fieldset class="space-y-2.5">
      <legend class="sr-only">انتخاب خدمت</legend>

      <?php foreach ($services as $i => $s): ?>
        <label class="pick rise rise-<?= min($i + 2, 5) ?> block relative tap">
          <input type="checkbox" name="service_ids[]" value="<?= (int) $s['id'] ?>"
                 class="sr-only" data-price="<?= (int) $s['price'] ?>"
                 data-minutes="<?= (int) $s['duration_minutes'] ?>"
                 <?= in_array((int) $s['id'], $selected, true) ? 'checked' : '' ?>>

          <span class="pick-card glass flex items-center gap-3.5 rounded-2xl px-4 py-3.5
                       transition-all duration-200 ease-out-soft hover:shadow-lift">

            <span class="pick-box w-6 h-6 shrink-0 rounded-lg border-2 border-ink-300 grid place-items-center
                         transition-colors duration-200" aria-hidden="true">
              <?= icon('check', 'pick-tick w-3.5 h-3.5 opacity-0 transition-opacity duration-200') ?>
            </span>

            <span class="flex-1 min-w-0">
              <span class="block text-[14px] font-bold text-ink-900 truncate"><?= e($s['name']) ?></span>
              <span class="block text-[12px] text-ink-500 mt-0.5 tabular-nums">
                <?= e(fa_num((int) $s['duration_minutes'])) ?> دقیقه
              </span>
            </span>

            <span class="text-[13px] font-extrabold text-ink-900 tabular-nums shrink-0">
              <?= e(toman((int) $s['price'])) ?>
            </span>
          </span>
        </label>
      <?php endforeach; ?>
    </fieldset>

    <!-- جمع انتخاب‌ها؛ تا چیزی انتخاب نشده دیده نمی‌شود -->
    <div id="svc-total" class="hidden glass rounded-2xl px-4 py-3 mt-3">
      <div class="flex items-center justify-between text-[13px]">
        <span class="text-ink-500">جمع</span>
        <span class="font-extrabold text-ink-900 tabular-nums" id="svc-sum"></span>
      </div>
      <div class="flex items-center justify-between text-[12px] mt-1">
        <span class="text-ink-400">مدت تقریبی</span>
        <span class="text-ink-600 tabular-nums" id="svc-dur"></span>
      </div>
    </div>

    <?php
    echo App\Core\View::render('components.sticky-action', [
        'label' => 'ادامه',
        'hint' => 'دست‌کم یک خدمت را انتخاب کن',
    ]);
    ?>
  </form>

  <script>
  (function () {
    const form = document.getElementById('svc-form');
    const box  = document.getElementById('svc-total');
    const sum  = document.getElementById('svc-sum');
    const dur  = document.getElementById('svc-dur');
    const fa   = n => String(n).replace(/[0-9]/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]);

    function update() {
      const picked = [...form.querySelectorAll('input[name="service_ids[]"]:checked')];
      if (!picked.length) { box.classList.add('hidden'); return; }

      const rials   = picked.reduce((t, i) => t + (+i.dataset.price), 0);
      const minutes = picked.reduce((t, i) => t + (+i.dataset.minutes), 0);

      sum.textContent = fa(Math.round(rials / 10).toLocaleString('en-US')) + ' تومان';
      dur.textContent = fa(minutes) + ' دقیقه';
      box.classList.remove('hidden');
    }

    form.addEventListener('change', update);
    update();
  })();
  </script>
<?php endif; ?>
