<?php /** @var ?string $error */ ?>
<h2 class="text-[15px] font-extrabold text-ink-900 mb-1">ورود به پنل</h2>
<p class="text-[13px] text-ink-500 mb-5 leading-relaxed">
  شمارهٔ موبایل شما همان نام کاربری‌تان است.
</p>

<?php if ($error): ?>
  <div role="alert"
       class="flex items-start gap-2 rounded-xl px-4 py-3 mb-4 text-[13px]"
       style="background:var(--bad-soft);color:var(--bad-strong)">
    <?= icon('alert', 'w-4 h-4 mt-0.5 shrink-0') ?>
    <span><?= e($error) ?></span>
  </div>
<?php endif; ?>

<form method="post" action="<?= e(url('login')) ?>" class="space-y-4">
  <?= csrf_field() ?>

  <div>
    <label class="block text-[13px] font-semibold text-ink-800 mb-1.5" for="phone">شمارهٔ موبایل</label>
    <input id="phone" type="tel" name="phone" inputmode="numeric" dir="ltr"
           autocomplete="username" placeholder="۰۹۱۲۳۴۵۶۷۸۹"
           value="<?= e((string) old('phone')) ?>"
           <?= old('phone') === '' ? 'autofocus' : '' ?>
           class="field field-lg text-left ltr tracking-wider">
  </div>

  <div>
    <label class="block text-[13px] font-semibold text-ink-800 mb-1.5" for="password">رمز عبور</label>
    <!--
      نمایش رمز، با دکمه. روی موبایل و با صفحه‌کلید فارسی/انگلیسی،
      تایپ رمز بدون دیدنش منبع اصلی «رمزم کار نمی‌کند» است.
    -->
    <div class="relative">
      <input id="password" type="password" name="password" autocomplete="current-password"
             <?= old('phone') !== '' ? 'autofocus' : '' ?>
             class="field field-lg ps-4 pe-12">
      <button type="button" id="pw-toggle" hidden
              class="absolute inset-y-0 end-0 w-12 grid place-items-center text-ink-400
                     rounded-xl cursor-pointer focus-visible:outline-2 focus-visible:outline-accent"
              aria-label="نمایش رمز" aria-pressed="false">
        <?= icon('eye', 'w-5 h-5') ?>
      </button>
    </div>
  </div>

  <button type="submit" class="btn-accent metal w-full h-12">ورود</button>
</form>

<!--
  کد پیامکی حذف نشده، فقط دوم شده.

  لازم است بماند: کسی که رمزش را فراموش کرده از همین‌جا وارد می‌شود و
  رمز تازه می‌گذارد. «فراموشی رمز» جداگانه نداریم چون ایمیل نداریم —
  و این مسیر همان کار را می‌کند، بدون لایهٔ اضافه.
-->
<div class="mt-5 pt-5" style="border-top:1px solid var(--line)">
  <form method="post" action="<?= e(url('login/otp')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="phone" id="otp-phone" value="<?= e((string) old('phone')) ?>">
    <button type="submit" class="tap w-full h-11 rounded-xl text-[13px] font-bold text-accent
                                 hover:bg-ink-50 transition-colors">
      رمز ندارید یا یادتان رفته؟ ورود با کد پیامکی
    </button>
  </form>
</div>

<script>
(function () {
  // شمارهٔ فرم بالا را به فرم کد پیامکی می‌رساند تا کاربر دوباره تایپش نکند.
  var phone = document.getElementById('phone');
  var mirror = document.getElementById('otp-phone');
  if (phone && mirror) {
    phone.addEventListener('input', function () { mirror.value = phone.value; });
  }

  // دکمهٔ نمایش رمز فقط با جاوااسکریپت معنی دارد، پس با آن هم ظاهر می‌شود.
  var btn = document.getElementById('pw-toggle');
  var pw = document.getElementById('password');
  if (btn && pw) {
    btn.hidden = false;
    btn.addEventListener('click', function () {
      var shown = pw.type === 'text';
      pw.type = shown ? 'password' : 'text';
      btn.setAttribute('aria-pressed', shown ? 'false' : 'true');
      btn.setAttribute('aria-label', shown ? 'نمایش رمز' : 'پنهان کردن رمز');
      pw.focus();
    });
  }
})();
</script>
