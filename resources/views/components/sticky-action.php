<?php
/**
 * نوار اقدامِ چسبیده به پایین صفحه.
 *
 * چرا: روی موبایل، دکمهٔ اصلی وسط اسکرول گم می‌شد. صفحهٔ رزرو ۲۰۸۰
 * پیکسل بود و دکمهٔ «ادامه» در ۱۸۱۰ — یعنی مشتری باید تا ته صفحه
 * می‌رفت تا بفهمد اصلاً دکمه‌ای هست. سنجش روی سه اندازهٔ موبایل نشان
 * داد در پنج صفحه دکمهٔ اصلی خارج از دید اول است.
 *
 * حالا نوار به پایینِ دید می‌چسبد و شست کاربر همیشه رویش است — پایین
 * صفحه، جایی که دست روی گوشی هست، نه بالای صفحه.
 *
 * `sticky` است و نه `fixed`: وقتی به انتهای فرم می‌رسیم سر جای خودش
 * می‌نشیند و روی محتوا نمی‌افتد.
 *
 * @var string      $label    متن دکمه
 * @var string|null $summary  خلاصهٔ انتخاب کاربر، بالای دکمه
 * @var string|null $hint     راهنمای کوتاه، وقتی هنوز چیزی انتخاب نشده
 * @var bool|null   $disabled دکمه خاموش باشد
 */
$summary = $summary ?? null;
$hint = $hint ?? null;
$disabled = $disabled ?? false;
?>
<div class="sticky-action sticky bottom-0 z-20 -mx-5 mt-5 px-5 pt-3 border-t"
     style="border-color:var(--line);
            padding-bottom:calc(0.75rem + env(safe-area-inset-bottom))">

  <!--
    خطِ بالای دکمه، سه کار می‌کند:

    ۱. تا وقتی چیزی انتخاب نشده، راهنماست.
    ۲. بعد از انتخاب، خودِ انتخاب را نشان می‌دهد — مشتری پیش از زدن
       دکمه می‌بیند چه چیزی را تأیید می‌کند.
    ۳. اگر دکمه بدون انتخاب زده شود، می‌شود پیام خطا.

    همیشه رندر می‌شود (حتی خالی) چون سومی جایی لازم دارد که از قبل
    در DOM باشد؛ ساختنش در لحظه، نوار را تکان می‌دهد.
  -->
  <p class="sticky-action-note text-[12px] mb-2 <?= $summary !== null ? 'text-ink-700 font-semibold' : 'text-ink-400' ?>"
     data-hint="<?= e($hint ?? '') ?>"
     <?= ($summary ?? $hint) === null ? 'hidden' : '' ?>>
    <?= e($summary ?? $hint ?? '') ?>
  </p>

  <button type="submit" class="btn-accent metal w-full"<?= $disabled ? ' disabled' : '' ?>>
    <?= e($label) ?>
    <?= icon('chevron-end', 'w-4 h-4') ?>
  </button>
</div>

<script>
/*
 * وقتی کاربر گزینه‌ای را انتخاب کرد، همان را در نوار پایین بنویس.
 *
 * بدون جاوااسکریپت هم صفحه کار می‌کند — آن‌وقت فقط متن راهنما
 * می‌ماند و دکمه سر جایش است. این فقط تأییدِ بصری است، نه شرطِ کار.
 */
(function () {
  var bar = document.currentScript.previousElementSibling;
  if (!bar) return;
  var note = bar.querySelector('.sticky-action-note');
  var form = bar.closest('form');
  if (!note || !form) return;

  var hint = note.getAttribute('data-hint') || '';

  function labelOf(input) {
    var wrap = input.closest('label');
    if (!wrap) return '';
    return (wrap.innerText || '').trim().split('\n')[0].trim();
  }

  function refresh() {
    var picked = [].slice.call(form.querySelectorAll('input:checked'))
      .map(labelOf).filter(Boolean);

    if (picked.length === 0) {
      note.textContent = hint;
      note.classList.add('text-ink-400');
      note.classList.remove('text-ink-700', 'font-semibold');
      return;
    }
    note.textContent = picked.join(' · ');
    note.classList.remove('text-ink-400');
    note.classList.add('text-ink-700', 'font-semibold');
  }

  form.addEventListener('change', refresh);
  refresh();

  /*
   * بن‌بستِ خاموش.
   *
   * ورودی‌های انتخاب (ساعت، خدمت، آرایشگر) همه `sr-only`اند — از چشم
   * پنهان‌اند و ظاهرِ کارت رویشان سوار است. وقتی `required` باشند و
   * کاربر بدون انتخاب دکمه را بزند، مرورگر می‌خواهد حبابِ خطا را کنار
   * ورودی نشان دهد، ورودی را نامرئی می‌بیند، و **هیچ کاری نمی‌کند**:
   * نه پیامی، نه اسکرولی، نه خطایی در کنسول. فرم هم ارسال نمی‌شود.
   *
   * برای کسی که اولین بار از روی QR وارد شده، این یعنی «این سایت
   * کار نمی‌کند» — و می‌رود. سنجیدیمش: دکمه زده می‌شد و صفحه
   * تکان نمی‌خورد.
   *
   * `invalid` بالا نمی‌رود (bubble نمی‌شود)، پس در فاز capture گرفته
   * می‌شود.
   */
  var errorTimer = null;

  form.addEventListener('invalid', function (e) {
    e.preventDefault();

    var field = e.target;

    note.hidden = false;
    note.textContent = field.dataset.missing || 'اول یکی را انتخاب کن';
    note.classList.remove('text-ink-400', 'text-ink-700', 'font-semibold');
    note.classList.add('text-bad', 'font-bold');

    /*
     * نشان را روی *اولین گزینه* می‌گذاریم، نه روی کل fieldset.
     *
     * فهرست سانس‌ها سه هزار پیکسل بلند است؛ خط قرمزِ دورش روی گوشی
     * فقط دو خط عمودی در لبه‌های صفحه می‌شود و پیامش این است که «کل
     * صفحه ایراد دارد» — نه «از این‌ها یکی را بردار». یک حلقهٔ کوچک
     * دور اولین گزینه، دقیقاً می‌گوید از کجا شروع کند.
     */
    var group = field.closest('fieldset') || field.closest('form');
    var target = group ? (group.querySelector('label') || group) : null;

    if (target) {
      target.classList.add('needs-pick');
      target.scrollIntoView({ behavior: 'smooth', block: 'center' });

      clearTimeout(errorTimer);
      errorTimer = setTimeout(function () {
        target.classList.remove('needs-pick');
      }, 1600);
    }
  }, true);

  // اولین انتخاب، حالت خطا را پاک می‌کند.
  form.addEventListener('change', function () {
    note.classList.remove('text-bad', 'font-bold');
    var marked = form.querySelector('.needs-pick');
    if (marked) { marked.classList.remove('needs-pick'); }
  });
})();
</script>
