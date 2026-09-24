<?php
/**
 * حساب کاربری.
 *
 * @var array $user
 * @var bool  $hasPassword
 */
?>
<h1 class="page-title mb-1">حساب کاربری</h1>
<p class="text-[13px] text-ink-400 mb-5">این صفحه مالِ خودِ شماست، نه سالن.</p>

<div class="grid lg:grid-cols-2 gap-4">

  <section class="glass rounded-2xl p-4">
    <h2 class="card-title mb-3">مشخصات</h2>

    <div class="flex items-center gap-3 mb-4">
      <?php $name = $user['name'] ?: 'بی‌نام'; $avatarColor = null; $avatarSize = 'w-12 h-12 text-[15px]';
            include BASE_PATH . '/resources/views/components/avatar.php'; ?>
      <div class="min-w-0">
        <div class="font-bold text-ink-800 truncate"><?= e($user['name'] ?: 'بی‌نام') ?></div>
        <!--
          شماره را نمی‌شود از اینجا عوض کرد، و این عمدی است: شماره
          هویت است — نوبت‌ها، پیامک‌ها و عضویت سالن به آن بسته‌اند.
          عوض کردنش یعنی ساختن یک آدم تازه، نه ویرایش یک فیلد.
        -->
        <div class="text-[12px] text-ink-400 code" dir="ltr">
          <?= e(phone_display((string) $user['phone'])) ?>
        </div>
      </div>
      <?php if ((int) $user['is_platform_admin'] === 1): ?>
        <span class="ms-auto shrink-0 text-[12px] rounded-full px-2.5 py-1 font-bold"
              style="background:var(--accent-soft);color:var(--accent)">مدیر کل</span>
      <?php endif; ?>
    </div>

    <form method="post" action="<?= e(url('panel/account/name')) ?>" class="space-y-3">
      <?= csrf_field() ?>
      <div>
        <label for="account-name" class="block text-[13px] font-semibold text-ink-800 mb-1.5">نام</label>
        <input id="account-name" name="name" value="<?= e($user['name'] ?? '') ?>" required
               class="field">
      </div>
      <button type="submit" class="btn-accent metal w-full h-11 text-[13px]">ذخیرهٔ نام</button>
    </form>
  </section>

  <section class="glass rounded-2xl p-4">
    <h2 class="card-title mb-1"><?= $hasPassword ? 'تغییر رمز عبور' : 'گذاشتن رمز عبور' ?></h2>
    <p class="text-[12px] text-ink-400 mb-3 leading-relaxed">
      <?php if ($hasPassword): ?>
        با شمارهٔ موبایل و همین رمز وارد می‌شوید.
      <?php else: ?>
        الان فقط با کد پیامکی وارد می‌شوید. با گذاشتن رمز، دیگر لازم نیست هر بار منتظر پیامک بمانید.
      <?php endif; ?>
    </p>

    <form method="post" action="<?= e(url('panel/account/password')) ?>" class="space-y-3">
      <?= csrf_field() ?>

      <?php if ($hasPassword): ?>
        <div>
          <label for="current-password" class="block text-[13px] font-semibold text-ink-800 mb-1.5">رمز فعلی</label>
          <input id="current-password" type="password" name="current_password" required
                 autocomplete="current-password"
                 class="field">
        </div>
      <?php endif; ?>

      <div>
        <label for="new-password" class="block text-[13px] font-semibold text-ink-800 mb-1.5">رمز تازه</label>
        <input id="new-password" type="password" name="new_password" required minlength="8"
               autocomplete="new-password"
               class="field">
        <p class="text-[12px] text-ink-400 mt-1">
          دست‌کم <?= e(fa_num(App\Domain\Identity\PasswordService::MIN_LENGTH)) ?> نویسه.
        </p>
      </div>

      <div>
        <label for="new-password2" class="block text-[13px] font-semibold text-ink-800 mb-1.5">تکرار رمز تازه</label>
        <input id="new-password2" type="password" name="new_password2" required minlength="8"
               autocomplete="new-password"
               class="field">
      </div>

      <button type="submit" class="btn-accent metal w-full h-11 text-[13px]">
        <?= $hasPassword ? 'تغییر رمز' : 'ذخیرهٔ رمز' ?>
      </button>
    </form>

    <?php if ($hasPassword): ?>
      <!--
        برداشتن رمز، پشت <details>. کاری است که کسی روزی یک بار
        نمی‌کند، و اگر کنار دکمهٔ «تغییر رمز» بنشیند، یک روز اشتباهی
        زده می‌شود.
      -->
      <details class="mt-4 pt-4" style="border-top:1px solid var(--line)">
        <summary class="tap cursor-pointer text-[12px] text-ink-500 list-none flex items-center gap-1.5">
          <?= icon('chevron-end', 'w-3.5 h-3.5') ?>
          می‌خواهم رمز را بردارم
        </summary>
        <form method="post" action="<?= e(url('panel/account/password/remove')) ?>" class="space-y-3 mt-3">
          <?= csrf_field() ?>
          <p class="text-[12px] text-ink-400 leading-relaxed">
            بعد از این فقط با کد پیامکی وارد می‌شوید. اگر پیامک تنظیم نشده باشد، راهی برای ورود نمی‌ماند.
          </p>
          <input type="password" name="current_password" required placeholder="رمز فعلی"
                 aria-label="رمز فعلی برای برداشتن رمز"
                 class="field">
          <button type="submit" class="tap w-full h-11 rounded-xl text-[13px] font-bold text-bad hover:bg-bad-soft">
            برداشتن رمز
          </button>
        </form>
      </details>
    <?php endif; ?>
  </section>
</div>
