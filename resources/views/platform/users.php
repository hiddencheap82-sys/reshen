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

<form method="get" action="<?= e(url('platform/users')) ?>" class="flex gap-2 mb-4">
  <label for="user-q" class="sr-only">جستجوی کاربر</label>
  <input type="search" id="user-q" name="q" value="<?= e($q) ?>" placeholder="شماره یا نام"
         class="flex-1 min-w-0 h-11 rounded-xl border border-ink-200 px-3 text-[13px]
                focus:outline-none focus:ring-2 focus:ring-accent">
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
          <span class="ms-auto text-[12px] text-ink-400 code shrink-0" dir="ltr"><?= e(fa_num($u['phone'])) ?></span>
        </div>

        <p class="text-[12px] text-ink-400 leading-relaxed mb-2">
          <?= $u['memberships'] !== null ? e($u['memberships']) : 'عضو هیچ سالنی نیست' ?>
        </p>

        <form method="post" action="<?= e(url('platform/users/' . $u['id'] . '/admin')) ?>">
          <?= csrf_field() ?>
          <button class="tap h-11 px-3 rounded-lg text-[12px] font-bold
                         <?= $u['is_platform_admin'] ? 'text-red-600 hover:bg-red-50' : 'text-accent hover:bg-ink-100' ?>">
            <?= $u['is_platform_admin'] ? 'گرفتن دسترسی مدیر پلتفرم' : 'مدیر پلتفرم کن' ?>
          </button>
        </form>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
