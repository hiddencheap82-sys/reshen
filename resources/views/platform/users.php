<?php
/**
 * کاربران پلتفرم.
 *
 * @var array $users
 * @var string $q
 */
$active = 'users';
include __DIR__ . '/_nav.php';
?>

<h1 class="page-title mb-4">کاربران</h1>

<!--
  ساخت کاربر، پشت <details>.

  کارِ هر روز نیست و اگر همیشه باز باشد، فهرست کاربران — که کارِ هر
  روز *هست* — هر بار یک صفحه پایین‌تر می‌افتد.
-->
<details class="glass rounded-2xl mb-4">
  <summary class="tap cursor-pointer list-none flex items-center gap-2 px-4 h-12 text-[13px] font-bold text-accent">
    <?= icon('plus', 'w-4 h-4') ?>
    کاربر تازه
  </summary>
  <form method="post" action="<?= e(url('platform/users')) ?>"
        class="px-4 pb-4 pt-1 space-y-3" style="border-top:1px solid var(--line)">
    <?= csrf_field() ?>
    <div class="grid sm:grid-cols-2 gap-3">
      <div>
        <label for="new-user-name" class="block text-[13px] font-semibold text-ink-800 mb-1.5">نام</label>
        <input id="new-user-name" name="name"
               class="field">
      </div>
      <div>
        <label for="new-user-phone" class="block text-[13px] font-semibold text-ink-800 mb-1.5">شمارهٔ موبایل</label>
        <input id="new-user-phone" name="phone" required dir="ltr" inputmode="numeric" placeholder="09123456789"
               class="field text-left">
      </div>
    </div>
    <div>
      <label for="new-user-password" class="block text-[13px] font-semibold text-ink-800 mb-1.5">
        رمز عبور <span class="font-normal text-ink-400">— اختیاری</span>
      </label>
      <input id="new-user-password" name="password" type="password" dir="ltr" autocomplete="new-password"
             class="field">
      <p class="text-[12px] text-ink-400 mt-1 leading-relaxed">
        خالی بگذارید تا این کاربر فقط با کد پیامکی وارد شود. اگر رمز بگذارید، خودتان باید به او بگویید.
      </p>
    </div>
    <label class="tap flex items-center gap-2 min-h-11 text-[13px] text-ink-600">
      <input type="checkbox" name="is_platform_admin" value="1" class="w-5 h-5 accent-current">
      مدیر پلتفرم باشد — به همهٔ سالن‌ها دسترسی دارد
    </label>
    <button type="submit" class="btn-accent metal w-full h-11 text-[13px]">ساخت کاربر</button>
  </form>
</details>

<form method="get" action="<?= e(url('platform/users')) ?>" class="flex gap-2 mb-4">
  <label for="user-q" class="sr-only">جستجوی کاربر</label>
  <input type="search" id="user-q" name="q" value="<?= e($q) ?>" placeholder="شماره یا نام"
         class="field flex-1 min-w-0">
  <button type="submit" class="btn-ink h-11 px-4 text-[13px]">جستجو</button>
</form>

<?php if ($users === []): ?>
  <div class="glass rounded-2xl py-12 text-center">
    <p class="text-sm text-ink-500">کاربری پیدا نشد.</p>
  </div>
<?php else: ?>
  <div class="glass rounded-2xl divide-y" style="border-color:var(--line)">
    <?php foreach ($users as $u): ?>
      <div class="px-4 py-3.5">
        <div class="flex items-center gap-2 mb-1">
          <span class="font-bold text-ink-900 min-w-0 truncate"><?= e($u['name'] ?? 'بی‌نام') ?></span>
          <?php if ($u['is_platform_admin']): ?>
            <span class="text-[12px] rounded-full px-2 py-0.5 shrink-0"
                  style="background:var(--accent-soft);color:var(--accent)">مدیر پلتفرم</span>
          <?php endif; ?>
          <?php if ($u['password_hash'] !== null): ?>
            <span class="shrink-0 text-ink-400" title="رمز دارد"><?= icon('key', 'w-3.5 h-3.5') ?></span>
          <?php endif; ?>
          <span class="ms-auto text-[12px] text-ink-400 code shrink-0" dir="ltr"><?= e(phone_display($u['phone'])) ?></span>
        </div>

        <p class="text-[12px] text-ink-400 leading-relaxed mb-2">
          <?= $u['memberships'] !== null ? e($u['memberships']) : 'عضو هیچ سالنی نیست' ?>
        </p>

        <div class="flex flex-wrap items-center gap-1">
          <form method="post" action="<?= e(url('platform/users/' . $u['id'] . '/admin')) ?>">
            <?= csrf_field() ?>
            <button class="tap h-11 px-3 rounded-lg text-[12px] font-bold
                           <?= $u['is_platform_admin'] ? 'text-bad hover:bg-bad-soft' : 'text-accent hover:bg-ink-100' ?>">
              <?= $u['is_platform_admin'] ? 'گرفتن دسترسی مدیر پلتفرم' : 'مدیر پلتفرم کن' ?>
            </button>
          </form>

          <!--
            رمز فعلی نمایش داده نمی‌شود چون قابل نمایش نیست — فقط
            hash ذخیره شده. این فرم رمز *تازه* می‌گذارد.
          -->
          <details class="w-full">
            <summary class="tap cursor-pointer list-none inline-flex items-center h-11 px-3
                            rounded-lg text-[12px] font-bold text-ink-500 hover:bg-ink-100">
              <?= $u['password_hash'] !== null ? 'عوض کردن رمز' : 'گذاشتن رمز' ?>
            </summary>
            <form method="post" action="<?= e(url('platform/users/' . $u['id'] . '/password')) ?>"
                  class="flex gap-2 mt-2">
              <?= csrf_field() ?>
              <label for="pw-<?= (int) $u['id'] ?>" class="sr-only">رمز تازه برای <?= e($u['name'] ?? $u['phone']) ?></label>
              <input id="pw-<?= (int) $u['id'] ?>" name="password" type="password" required minlength="8"
                     dir="ltr" autocomplete="new-password" placeholder="رمز تازه"
                     class="field flex-1 min-w-0">
              <button type="submit" class="btn-ink h-11 px-4 text-[12px] shrink-0">ذخیره</button>
            </form>
          </details>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
