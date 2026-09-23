<?php
/**
 * سؤال تازه.
 *
 * دو فیلد، نه بیشتر. هر انتخابِ اضافه — دسته‌بندی، اولویت — یعنی
 * صاحب سالن باید تصمیمی بگیرد که ما بهتر می‌توانیم از متنش بفهمیم.
 */
?>

<div class="flex items-center gap-2 mb-5">
  <a href="<?= e(url('panel/support')) ?>"
     class="w-11 h-11 grid place-items-center rounded-xl text-ink-500 hover:bg-ink-100 tap shrink-0"
     aria-label="بازگشت"><?= icon('chevron-start', 'w-4 h-4') ?></a>
  <div class="min-w-0">
    <h1 class="page-title">سؤال تازه</h1>
    <p class="text-[12px] text-ink-400 mt-0.5">معمولاً همان روز جواب می‌گیرید.</p>
  </div>
</div>

<form method="post" action="<?= e(url('panel/support')) ?>"
      class="glass rounded-2xl p-4 sm:p-5 space-y-4 max-w-xl">
  <?= csrf_field() ?>

  <div>
    <label for="subject" class="block text-[12px] font-bold text-ink-600 mb-1.5">موضوع</label>
    <input type="text" name="subject" id="subject" required maxlength="150"
           placeholder="مثلاً: پیامک یادآوری برای مشتری‌ها نمی‌رود" class="field">
  </div>

  <div>
    <label for="body" class="block text-[12px] font-bold text-ink-600 mb-1.5">توضیح</label>
    <textarea name="body" id="body" rows="6" required
              placeholder="هرچه بیشتر توضیح دهید، زودتر جواب می‌گیرید: چه کاری کردید، چه انتظاری داشتید، و چه شد."
              class="field"></textarea>
  </div>

  <button type="submit" class="btn-accent metal w-full">ثبت سؤال</button>
</form>
