<?php
use App\Core\Auth;
$role = Auth::role();
$salonName = App\Core\Session::get('_salon_name');
if (Auth::isImpersonating()) {
    $salonName = App\Core\DB::selectOne('SELECT name FROM salons WHERE id = ?', [Auth::salonId()])['name'] ?? $salonName;
}
$navItems = [
    ['href' => '/panel', 'label' => 'صف زنده', 'icon' => 'queue', 'roles' => ['owner','manager','staff','reception']],
    ['href' => '/panel/customers', 'label' => 'مشتریان', 'icon' => 'users', 'roles' => ['owner','manager','staff','reception']],
    ['href' => '/panel/bookings', 'label' => 'رزروها', 'icon' => 'calendar', 'roles' => ['owner','manager','reception']],
    ['href' => '/panel/reports', 'label' => 'گزارش‌ها', 'icon' => 'chart', 'roles' => ['owner','manager']],
    ['href' => '/panel/staff', 'label' => 'آرایشگرها', 'icon' => 'scissors', 'roles' => ['owner','manager']],
    ['href' => '/panel/services', 'label' => 'خدمات', 'icon' => 'tag', 'roles' => ['owner','manager']],
    ['href' => '/panel/settings', 'label' => 'تنظیمات سالن', 'icon' => 'cog', 'roles' => ['owner','manager']],
];
$visibleNav = array_values(array_filter($navItems, fn($i) => in_array($role, $i['roles'], true)));
$currentPath = '/' . trim($_SERVER['REQUEST_URI'] ?? '', '/');
$icon = function (string $name, string $class = 'w-5 h-5') {
    $paths = [
        'queue' => 'M4 6h16M4 12h16M4 18h7',
        'users' => 'M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 100-8 4 4 0 000 8zm6 0a4 4 0 100-8',
        'calendar' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
        'chart' => 'M9 19v-6a2 2 0 012-2h2a2 2 0 012 2v6m-9 0h14a1 1 0 001-1V6a1 1 0 00-1-1H5a1 1 0 00-1 1v12a1 1 0 001 1z',
        'scissors' => 'M6 9a3 3 0 100-6 3 3 0 000 6zm0 12a3 3 0 100-6 3 3 0 000 6zm12-15L6 18M9 9l9 9',
        'tag' => 'M7 7h.01M7 3h5.586a1 1 0 01.707.293l6.414 6.414a1 1 0 010 1.414l-8.586 8.586a1 1 0 01-1.414 0L3.293 13.293A1 1 0 013 12.586V7a4 4 0 014-4z',
        'cog' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z',
    ];
    return '<svg xmlns="http://www.w3.org/2000/svg" class="'.$class.'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="'.($paths[$name] ?? '').'"/></svg>';
};
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($title ?? 'رشن') ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<script>
  tailwind.config = { theme: { extend: {
    fontFamily: { sans: ['Vazirmatn','Tahoma','sans-serif'] },
    colors: { brand: { 50:'#eff6ff',100:'#dbeafe',500:'#3b82f6',600:'#2563eb',700:'#1d4ed8',900:'#1e3a8a' } }
  } } }
</script>
<link rel="manifest" href="<?= url('manifest.webmanifest') ?>">
<link rel="icon" href="<?= asset('icons/icon.svg') ?>" type="image/svg+xml">
<link rel="apple-touch-icon" href="<?= asset('icons/icon.svg') ?>">
<meta name="theme-color" content="#2563eb">
<style>
  body{font-family:'Vazirmatn','Tahoma',sans-serif;}
  .nav-active{ background:#eff6ff; color:#1d4ed8; font-weight:700; }
</style>
<script>
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => navigator.serviceWorker.register('<?= url('service-worker.js') ?>'));
  }
</script>
</head>
<body class="bg-slate-50 text-slate-900 antialiased">

<div class="flex min-h-screen">
  <!-- Desktop sidebar -->
  <aside class="hidden md:flex md:flex-col w-60 shrink-0 border-l border-slate-200 bg-white">
    <div class="h-16 flex items-center gap-2 px-5 border-b border-slate-100">
      <div class="w-9 h-9 rounded-xl bg-brand-600 text-white flex items-center justify-center font-bold">ر</div>
      <div>
        <div class="font-bold text-sm leading-tight"><?= e($salonName ?? 'رشن') ?></div>
        <div class="text-[11px] text-slate-400"><?= e(['owner'=>'صاحب سالن','manager'=>'مدیر','staff'=>'آرایشگر','reception'=>'پذیرش'][$role] ?? '') ?></div>
      </div>
    </div>
    <nav class="flex-1 py-4 px-3 space-y-1 overflow-y-auto">
      <?php foreach ($visibleNav as $item): $active = str_starts_with($currentPath, url($item['href'] === '/panel' ? '/panel' : $item['href'])) && ($item['href']!=='/panel' || $currentPath===rtrim(url('/panel'),'/')); ?>
      <a href="<?= url($item['href']) ?>" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-slate-600 hover:bg-slate-50 <?= $active ? 'nav-active' : '' ?>">
        <?= $icon($item['icon']) ?>
        <span><?= e($item['label']) ?></span>
      </a>
      <?php endforeach; ?>
    </nav>
    <div class="p-3 border-t border-slate-100">
      <?php if (Auth::isPlatformAdmin()): ?>
      <a href="<?= url('platform') ?>" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-amber-600 hover:bg-amber-50">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        پنل پلتفرم
      </a>
      <?php endif; ?>
      <a href="<?= url('logout') ?>" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-slate-500 hover:bg-slate-50">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
        خروج
      </a>
    </div>
  </aside>

  <!-- Main column -->
  <div class="flex-1 flex flex-col min-w-0">
    <!-- Mobile top bar -->
    <header class="md:hidden sticky top-0 z-20 h-14 bg-white border-b border-slate-200 flex items-center justify-between px-4">
      <div class="flex items-center gap-2">
        <div class="w-8 h-8 rounded-lg bg-brand-600 text-white flex items-center justify-center font-bold text-sm">ر</div>
        <span class="font-bold text-sm"><?= e($salonName ?? 'رشن') ?></span>
      </div>
      <a href="<?= url('logout') ?>" class="text-slate-400">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
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

    <main class="flex-1 p-4 md:p-6 pb-24 md:pb-6">
      <?= $content ?>
    </main>
  </div>
</div>

<!-- Mobile bottom nav -->
<nav class="md:hidden fixed bottom-0 inset-x-0 z-20 bg-white border-t border-slate-200 flex items-stretch h-16 px-1">
  <?php foreach (array_slice($visibleNav, 0, 5) as $item): $active = $currentPath===rtrim(url($item['href']),'/') || ($item['href']==='/panel' && $currentPath===rtrim(url('/panel'),'/')); ?>
  <a href="<?= url($item['href']) ?>" class="flex-1 flex flex-col items-center justify-center gap-0.5 text-[11px] <?= $active ? 'text-brand-600' : 'text-slate-400' ?>">
    <?= $icon($item['icon'], 'w-5 h-5') ?>
    <span><?= e($item['label']) ?></span>
  </a>
  <?php endforeach; ?>
</nav>

</body>
</html>
