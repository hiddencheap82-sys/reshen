<?php /** @var array $customers @var string $q */ ?>
<h1 class="page-title mb-4">مشتریان</h1>

<form method="get" action="<?= e(url('panel/customers')) ?>" class="mb-4">
  <label for="customer-search" class="sr-only">جستجوی مشتری با نام یا شماره</label>
  <!--
    آیکون داخل کادر، نه کنارش: هر اپ موبایلی همین را دارد و چشم بدون
    خواندنِ placeholder می‌فهمد این کادر جستجوست.
  -->
  <div class="relative">
    <span class="absolute inset-y-0 start-0 ps-3.5 grid place-items-center text-ink-400 pointer-events-none"
          aria-hidden="true"><?= icon('search', 'w-4 h-4') ?></span>
    <input type="search" id="customer-search" name="q" value="<?= e($q) ?>"
      placeholder="جستجو با نام یا شماره..." enterkeyhint="search"
      class="field ps-10 pe-4">
  </div>
</form>

<?php if (empty($customers)): ?>
  <div class="glass rounded-2xl p-10 text-center">
    <span class="w-12 h-12 mx-auto mb-3 rounded-2xl grid place-items-center text-ink-400"
          style="background:var(--fill-secondary)" aria-hidden="true"><?= icon('users', 'w-6 h-6') ?></span>
    <p class="text-[13px] text-ink-500">
      <?= $q === '' ? 'هنوز مشتری‌ای ثبت نشده.' : 'با «' . e($q) . '» چیزی پیدا نشد.' ?>
    </p>
    <?php if ($q !== ''): ?>
      <a href="<?= e(url('panel/customers')) ?>"
         class="tap inline-flex items-center h-11 px-4 mt-2 text-[13px] font-bold text-accent">
        نمایش همه
      </a>
    <?php endif; ?>
  </div>
<?php else: ?>
<div class="glass rounded-2xl overflow-hidden">
  <?php foreach ($customers as $c): ?>
  <a href="<?= e(url('panel/customers/' . $c['id'])) ?>"
     class="tap flex items-center gap-3 px-4 py-3 hover:bg-ink-50
            border-b last:border-b-0" style="border-color:var(--line)">
    <?php $name = $c['name'] ?: 'بدون نام'; $avatarColor = null;
          include BASE_PATH . '/resources/views/components/avatar.php'; ?>
    <span class="flex-1 min-w-0">
      <span class="block font-bold text-[14px] text-ink-800 truncate"><?= e($c['name'] ?: 'بدون نام') ?></span>
      <span class="block text-[12px] text-ink-400 code" dir="ltr">
        <?= $c['phone'] ? e(phone_display($c['phone'])) : '—' ?>
      </span>
    </span>
    <span class="text-end shrink-0">
      <span class="block text-[12px] font-bold text-ink-600 tabular-nums">
        <?= e(fa_num($c['visit_count'])) ?> مراجعه
      </span>
      <?php if ($c['last_visit_at']): ?>
        <span class="block text-[12px] text-ink-400 tabular-nums"><?= e(jdate($c['last_visit_at'], 'Y/m/d')) ?></span>
      <?php endif; ?>
    </span>
    <?= icon('chevron-end', 'w-4 h-4 text-ink-400 shrink-0') ?>
  </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>
