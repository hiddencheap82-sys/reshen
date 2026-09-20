<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($title ?? 'رشن') ?></title>
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
<link rel="manifest" href="<?= url('manifest.webmanifest') ?>">
<link rel="icon" href="<?= asset('icons/icon.svg') ?>" type="image/svg+xml">
<meta name="theme-color" content="#2563eb">
<style>body{font-family:'Vazirmatn','Tahoma',sans-serif;}</style>
</head>
<body class="bg-slate-50 text-slate-900 min-h-screen">
<div class="max-w-md mx-auto min-h-screen bg-white shadow-sm flex flex-col">
  <?php if (!empty($salon)): ?>
  <div class="bg-gradient-to-l from-brand-700 to-brand-600 text-white px-5 pt-6 pb-5">
    <div class="text-xs opacity-70 mb-1">رشن</div>
    <div class="text-lg font-extrabold"><?= e($salon['name']) ?></div>
    <?php if (!empty($salon['city'])): ?><div class="text-xs opacity-80 mt-0.5"><?= e($salon['city']) ?><?= !empty($salon['address']) ? ' · ' . e($salon['address']) : '' ?></div><?php endif; ?>
  </div>
  <?php endif; ?>
  <div class="flex-1 p-5">
    <?= $content ?>
  </div>
  <div class="text-center text-[11px] text-slate-300 pb-4">رشن — زمانِ راست می‌گوید</div>
</div>
</body>
</html>
