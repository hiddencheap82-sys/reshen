<?php
/**
 * @var array $salon
 * @var array $services
 * @var array $liveStatus  ['open'=>bool,'waiting'=>int,'freeNow'=>int,'chairs'=>int]
 */
?>

<?php if ($error = flash('error')): ?>
  <div role="alert"
       class="flex items-start gap-2 bg-red-50 text-red-800 text-sm rounded-xl px-4 py-3 mb-4 border border-red-100">
    <?= icon('alert', 'w-4 h-4 mt-0.5 shrink-0') ?>
    <span><?= e($error) ?></span>
  </div>
<?php endif; ?>

<!--
  وضعیت زندهٔ صف — تمایز اصلی محصول.

  این اولین چیزی است که مشتری می‌بیند، چون جوابِ سؤالی است که واقعاً
  در ذهنش دارد: «الان برم یا شلوغه؟» رقبا این را ندارند چون دادهٔ
  لحظه‌ای صف را ندارند.
-->
<?php if ($liveStatus['open']): ?>
  <div class="rise glass rounded-2xl overflow-hidden mb-5 hairline-accent">
    <div class="px-4 py-3.5 flex items-center gap-3">
      <span class="relative flex w-2.5 h-2.5 shrink-0" aria-hidden="true">
        <span class="absolute inline-flex w-full h-full rounded-full bg-green-500 opacity-60 animate-ping"></span>
        <span class="relative inline-flex w-2.5 h-2.5 rounded-full bg-green-600"></span>
      </span>

      <div class="flex-1 min-w-0">
        <?php if ($liveStatus['freeNow'] > 0): ?>
          <p class="text-sm font-bold text-green-700">همین حالا آزاد است</p>
          <p class="text-[12px] text-ink-500 mt-0.5">
            <?= e(fa_num($liveStatus['freeNow'])) ?> صندلی خالی — می‌توانی همین الان بیایی
          </p>
        <?php elseif ($liveStatus['waiting'] === 0): ?>
          <p class="text-sm font-bold text-ink-900">باز است</p>
          <p class="text-[12px] text-ink-500 mt-0.5">کسی در صف نیست</p>
        <?php else: ?>
          <p class="text-sm font-bold text-ink-900">
            <?= e(fa_num($liveStatus['waiting'])) ?> نفر در صف
          </p>
          <p class="text-[12px] text-ink-500 mt-0.5">
            <?= e($liveStatus['waitLabel']) ?>
          </p>
        <?php endif; ?>
      </div>

      <span class="text-[10.5px] font-semibold text-ink-400 shrink-0">زنده</span>
    </div>
  </div>
<?php else: ?>
  <div class="rise glass rounded-2xl px-4 py-3.5 mb-5 flex items-center gap-3">
    <span class="w-2.5 h-2.5 rounded-full bg-ink-300 shrink-0" aria-hidden="true"></span>
    <div>
      <p class="text-sm font-bold text-ink-700">الان بسته است</p>
      <p class="text-[12px] text-ink-500 mt-0.5">می‌توانی برای روزهای بعد نوبت بگیری</p>
    </div>
  </div>
<?php endif; ?>

<div class="rise rise-1">
  <h2 class="text-[15px] font-extrabold text-ink-900 mb-1">چه خدمتی می‌خواهی؟</h2>
  <p class="text-[13px] text-ink-500 mb-4">می‌توانی چند مورد را با هم انتخاب کنی.</p>
</div>

<?php if (empty($services)): ?>
  <div class="glass rounded-2xl py-12 px-5 text-center">
    <?= icon('scissors', 'w-10 h-10 mx-auto text-ink-300 mb-3') ?>
    <p class="text-sm font-semibold text-ink-600">هنوز خدمتی تعریف نشده</p>
    <p class="text-[12.5px] text-ink-400 mt-1">با خود آرایشگاه تماس بگیرید.</p>
  </div>

<?php else: ?>
  <form method="post" action="<?= e(url('s/' . $salon['slug'])) ?>" id="svc-form">
    <?= csrf_field() ?>

    <fieldset class="space-y-2.5">
      <legend class="sr-only">انتخاب خدمت</legend>

      <?php foreach ($services as $i => $s): ?>
        <label class="pick rise rise-<?= min($i + 2, 5) ?> block relative tap">
          <input type="checkbox" name="service_ids[]" value="<?= (int) $s['id'] ?>"
                 class="sr-only" data-price="<?= (int) $s['price'] ?>"
                 data-minutes="<?= (int) $s['duration_minutes'] ?>">

          <span class="pick-card glass flex items-center gap-3.5 rounded-2xl px-4 py-3.5
                       transition-all duration-200 ease-out-soft hover:shadow-lift">

            <span class="pick-box w-6 h-6 shrink-0 rounded-lg border-2 border-ink-300 grid place-items-center
                         transition-colors duration-200" aria-hidden="true">
              <?= icon('check', 'pick-tick w-3.5 h-3.5 opacity-0 transition-opacity duration-200') ?>
            </span>

            <span class="flex-1 min-w-0">
              <span class="block text-[14.5px] font-bold text-ink-900 truncate"><?= e($s['name']) ?></span>
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

    <button type="submit" class="btn-accent metal w-full mt-4">
      ادامه
      <?= icon('chevron-end', 'w-4 h-4') ?>
    </button>
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
