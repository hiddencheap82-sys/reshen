<?php
use App\Core\Auth;
use App\Domain\Access\Access;
$role = Auth::role();
$salonName = App\Core\Session::get('_salon_name');
if (Auth::isImpersonating()) {
    $salonName = App\Core\DB::selectOne('SELECT name FROM salons WHERE id = ?', [Auth::salonId()])['name'] ?? $salonName;
}
/*
 * منو از همان سیاستی می‌خواند که مسیرها را می‌بندد.
 *
 * پیش از این فهرست نقش‌ها اینجا دوباره نوشته شده بود و با مسیرها فرق
 * کرده بود: «مشتریان» به آرایشگر نشان داده می‌شد. لینکی که بزنی و
 * ۴۰۳ بگیری، خودش یک ایراد است.
 *
 * ability برابر null یعنی هر کسی که عضو سالن است.
 */
$navItems = [
    /*
     * داشبورد اول می‌آید ولی فقط برای صاحب و مدیر. آرایشگر و پذیرش
     * اصلاً نمی‌بینندش و تبِ اولشان همان «صف زنده» می‌ماند — چیزی که
     * واقعاً وسط کار لازمشان است.
     */
    ['href' => '/panel/dashboard','label' => 'داشبورد',       'icon' => 'home',     'ability' => Access::MANAGE_SALON],
    ['href' => '/panel',          'label' => 'صف زنده',       'icon' => 'queue',    'ability' => null],
    ['href' => '/panel/bookings', 'label' => 'رزروها',        'icon' => 'calendar', 'ability' => null],
    ['href' => '/panel/customers','label' => 'مشتریان',       'icon' => 'users',    'ability' => Access::VIEW_CUSTOMERS],
    ['href' => '/panel/reports',  'label' => 'گزارش‌ها',       'icon' => 'chart',    'ability' => Access::MANAGE_SALON],
    ['href' => '/panel/staff',    'label' => 'آرایشگرها',     'icon' => 'scissors', 'ability' => Access::MANAGE_SALON],
    ['href' => '/panel/services', 'label' => 'خدمات',         'icon' => 'tag',      'ability' => Access::MANAGE_SALON],
    ['href' => '/panel/qr',       'label' => 'کد QR',         'icon' => 'qr',       'ability' => null],
    ['href' => '/panel/sms',      'label' => 'الگوی پیامک',   'icon' => 'message',  'ability' => Access::MANAGE_SALON],
    ['href' => '/panel/settings', 'label' => 'تنظیمات سالن',  'icon' => 'cog',      'ability' => Access::MANAGE_SALON],
];
$visibleNav = array_values(array_filter(
    $navItems,
    static fn ($i) => $i['ability'] === null || Access::allows($i['ability'])
));
$currentPath = '/' . trim($_SERVER['REQUEST_URI'] ?? '', '/');

/*
 * در پنل پلتفرم، ناوبری سالن کنار می‌رود.
 *
 * چرا: نوار پایین می‌گوید «صف زنده» و «رزروها»، ولی آن‌ها مال *یک*
 * سالن‌اند — سالنی که مدیر پلتفرم اتفاقاً عضوش است. وقتی داری همهٔ
 * سالن‌ها را می‌بینی، آن نوار می‌گوید در جایی هستی که نیستی.
 *
 * به‌جایش یک راه بازگشت روشن می‌ماند، چون مدیر پلتفرم معمولاً صاحب
 * سالن هم هست و باید بتواند برگردد.
 */
$onPlatform = str_starts_with($currentPath, '/platform');
?>
<!doctype html>
<html lang="fa" dir="rtl" data-font="<?= e((string) App\Core\Config::get('reshen.ui.font', 'vazirmatn')) ?>" <?= theme_attr(App\Core\Session::get('_salon_theme')) ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($title ?? 'رشن') ?></title>
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
<?php include BASE_PATH . '/resources/views/components/pwa-head.php'; ?>
<meta name="theme-color" content="#1C1917">
<style>
  .nav-active{ background:var(--accent-soft); color:var(--accent); font-weight:700; }
</style>
<?php include BASE_PATH . '/resources/views/components/theme-boot.php'; ?>
</head>
<body class="antialiased">

<?php include BASE_PATH . '/resources/views/components/icons.svg'; ?>

<div class="flex min-h-screen">
  <!-- Desktop sidebar -->
  <aside class="hidden md:flex md:flex-col w-60 shrink-0 border-l border-ink-200 bg-white">
    <div class="h-16 flex items-center gap-2 px-5 border-b border-ink-100">
      <div class="w-9 h-9 rounded-xl bg-ink-900 text-white flex items-center justify-center font-bold">ر</div>
      <div>
        <div class="font-bold text-sm leading-tight"><?= e($salonName ?? 'رشن') ?></div>
        <div class="text-[12px] text-ink-400"><?= e(['owner'=>'صاحب سالن','manager'=>'مدیر','staff'=>'آرایشگر','reception'=>'پذیرش'][$role] ?? '') ?></div>
      </div>
    </div>
    <nav class="flex-1 py-4 px-3 space-y-1 overflow-y-auto">
      <?php foreach ($visibleNav as $item): $active = str_starts_with($currentPath, url($item['href'] === '/panel' ? '/panel' : $item['href'])) && ($item['href']!=='/panel' || $currentPath===rtrim(url('/panel'),'/')); ?>
      <a href="<?= url($item['href']) ?>" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-ink-600 hover:bg-ink-50 <?= $active ? 'nav-active' : '' ?>">
        <?= nav_icon($item['icon'], $active) ?>
        <span><?= e($item['label']) ?></span>
      </a>
      <?php endforeach; ?>
    </nav>
    <div class="p-3 border-t border-ink-100">
      <?php if (Auth::isPlatformAdmin()): ?>
      <a href="<?= url('platform') ?>" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-amber-600 hover:bg-amber-50">
        <?= icon('shield', 'w-5 h-5') ?>
        پنل پلتفرم
      </a>
      <?php endif; ?>
      <a href="<?= url('logout') ?>" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-ink-500 hover:bg-ink-50">
        <?= icon('logout', 'w-5 h-5') ?>
        خروج
      </a>
    </div>
  </aside>

  <!-- Main column -->
  <div class="flex-1 flex flex-col min-w-0">
    <!-- Mobile top bar -->
    <header class="md:hidden sticky top-0 z-20 h-14 bg-white border-b border-ink-200 flex items-center justify-between px-4">
      <!--
        min-w-0 و truncate لازم‌اند: بدون آن‌ها نام بلندِ سالن کوتاه
        نمی‌شود و کل سرصفحه را پهن‌تر از صفحه می‌کند. روی صفحهٔ ۳۲۰
        پیکسلی، همین ۲۳ پیکسل سرریز افقی می‌ساخت.
      -->
      <div class="flex items-center gap-2 min-w-0 flex-1">
        <div class="w-8 h-8 shrink-0 rounded-lg metal metal-ink grid place-items-center font-bold text-sm">ر</div>
        <span class="font-bold text-[13px] truncate"><?= e($salonName ?? 'رشن') ?></span>
      </div>
      <!--
        خروج: پیش از این یک آیکون ۲۰×۲۰ بدون نام دسترس‌پذیر بود — صفحه‌خوان
        فقط «لینک» می‌خواند و انگشت هم به‌سختی می‌گرفتش.
      -->
      <?php include BASE_PATH . '/resources/views/components/theme-toggle.php'; ?>
      <a href="<?= e(url('logout')) ?>" aria-label="خروج از حساب" title="خروج"
         class="w-11 h-11 grid place-items-center rounded-xl text-ink-500
                hover:bg-ink-100 transition-colors cursor-pointer
                focus-visible:outline-2 focus-visible:outline-ink-400">
        <?= icon('logout', 'w-5 h-5') ?>
      </a>
    </header>

    <?php if (Auth::isImpersonating()): ?>
      <div class="bg-amber-500 text-white text-xs px-4 py-2 flex items-center justify-between">
        <span>حالت پشتیبانی — در حال مشاهدهٔ «<?= e($salonName) ?>»</span>
        <a href="<?= url('platform/impersonate/stop') ?>" class="underline font-bold">خروج</a>
      </div>
    <?php endif; ?>
    <?php if ($success = flash('success')): ?>
      <div class="bg-emerald-50 text-emerald-700 text-sm px-4 py-2.5 border-b border-emerald-100"><?= e($success) ?></div>
    <?php endif; ?>
    <?php if ($error = flash('error')): ?>
      <div class="bg-red-50 text-red-700 text-sm px-4 py-2.5 border-b border-red-100"><?= e($error) ?></div>
    <?php endif; ?>

    <main class="flex-1 p-4 md:p-6 md:pb-6"
          <?php if (!$onPlatform): ?>
          style="padding-bottom:calc(5.5rem + env(safe-area-inset-bottom,0px))"
          <?php endif; ?>>
      <?= $content ?>
    </main>
  </div>
</div>

<!--
  ناوبری موبایل.
  چهار مورد اول مستقیم، بقیه پشت «بیشتر».

  چرا: صاحب سالن هشت گزینه دارد و کفِ صفحه جای چهار تا پنج تا بیشتر
  نیست. پیش‌تر فهرست با array_slice بریده می‌شد و گزینه‌های آخر —
  خدمات، کد QR و تنظیمات — روی موبایل **اصلاً در دسترس نبودند**.

  <details> است نه جاوااسکریپت: بدون اسکریپت هم باز و بسته می‌شود.
-->
<?php
  $navPrimary = array_slice($visibleNav, 0, 4);
  $navRest = array_slice($visibleNav, 4);
  $isActive = fn (array $i) => $currentPath === rtrim(url($i['href']), '/');
?>
<?php if ($onPlatform): ?>
  <!-- راه بازگشت به پنل سالن، به‌جای ناوبری‌ای که به اینجا ربطی ندارد. -->
  <div class="md:hidden px-4 pb-6">
    <a href="<?= e(url('panel')) ?>"
       class="tap glass rounded-2xl px-4 h-12 flex items-center gap-2 text-[13px] font-semibold text-ink-600">
      <?= icon('chevron-start', 'w-4 h-4') ?>
      بازگشت به پنل آرایشگاه
    </a>
  </div>
<?php else: ?>
<nav class="md:hidden fixed bottom-0 inset-x-0 z-30" aria-label="ناوبری اصلی">

  <?php if ($navRest !== []): ?>
    <details class="group" id="more-nav">
      <summary class="list-none [&::-webkit-details-marker]:hidden"></summary>

      <!-- پس‌زمینه: کلیک بیرون، شیت را می‌بندد -->
      <label for="more-nav" class="fixed inset-0 bg-ink-950/45 backdrop-blur-[2px]"
             onclick="document.getElementById('more-nav').removeAttribute('open')"
             aria-hidden="true"></label>

      <!--
        min-w-0 روی خانه‌های گرید لازم است: خانهٔ گرید به‌طور پیش‌فرض
        min-width:auto دارد و زیر عرضِ محتوایش کوچک نمی‌شود. برچسبی مثل
        «تنظیمات سالن» شیت را پهن‌تر از صفحه می‌کرد و چون شیت داخل یک
        nav با inset-x-0 است، کل صفحه سرریز افقی می‌گرفت.
      -->
      <div class="glass-bar absolute bottom-full inset-x-0 max-w-full rounded-t-2xl p-2 shadow-deep">
        <div class="grid grid-cols-3 gap-1.5">
          <?php foreach ($navRest as $item): ?>
            <a href="<?= e(url($item['href'])) ?>"
               class="min-w-0 flex flex-col items-center justify-center gap-1 h-[4.5rem] rounded-xl tap
                      text-[12px] font-semibold <?= $isActive($item) ? 'text-accent' : 'text-ink-600' ?>"
               <?= $isActive($item) ? 'aria-current="page"' : '' ?>
               style="<?= $isActive($item) ? 'background:var(--accent-soft)' : '' ?>">
              <?= nav_icon($item['icon'], $isActive($item), 'w-5 h-5 shrink-0') ?>
              <span class="max-w-full truncate px-1"><?= e($item['label']) ?></span>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    </details>
  <?php endif; ?>

  <div class="glass-bar flex items-stretch h-16 px-1"
       style="padding-bottom:env(safe-area-inset-bottom,0px)">
    <?php foreach ($navPrimary as $item): ?>
      <?php $on = $isActive($item); ?>
      <a href="<?= e(url($item['href'])) ?>"
         class="tab-item flex-1 min-w-0 flex flex-col items-center justify-center gap-0.5 text-[12px]
                <?= $on ? 'text-accent font-bold' : 'text-ink-400' ?>"
         <?= $on ? 'aria-current="page"' : '' ?>>
        <span class="tab-dot <?= $on ? 'tab-dot-on' : '' ?>" aria-hidden="true"></span>
        <?= nav_icon($item['icon'], $on, 'w-5 h-5 shrink-0') ?>
        <span class="max-w-full truncate px-0.5"><?= e($item['label']) ?></span>
      </a>
    <?php endforeach; ?>

    <?php if ($navRest !== []): ?>
      <?php $restActive = array_filter($navRest, $isActive) !== []; ?>
      <button type="button"
              onclick="var d=document.getElementById('more-nav'); d.open ? d.removeAttribute('open') : d.setAttribute('open','');"
              class="flex-1 min-w-0 flex flex-col items-center justify-center gap-0.5 text-[12px] cursor-pointer
                     <?= $restActive ? 'text-accent font-bold' : 'text-ink-400' ?>"
              aria-label="گزینه‌های بیشتر">
        <span class="tab-dot <?= $restActive ? 'tab-dot-on' : '' ?>" aria-hidden="true"></span>
        <?= nav_icon('more', $restActive, 'w-5 h-5') ?>
        <span>بیشتر</span>
      </button>
    <?php endif; ?>
  </div>
</nav>
<?php endif; ?>

<?php include BASE_PATH . '/resources/views/components/install-prompt.php'; ?>

</body>
</html>
