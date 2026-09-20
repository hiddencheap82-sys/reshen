<!doctype html>
<html lang="fa" dir="rtl" data-font="<?= e((string) App\Core\Config::get('reshen.ui.font', 'vazirmatn')) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($title ?? 'رشن') ?></title>
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
<link rel="manifest" href="<?= url('manifest.webmanifest') ?>">
<link rel="icon" href="<?= asset('icons/icon.svg') ?>" type="image/svg+xml">
<meta name="theme-color" content="#1d4ed8">
<style>
  body{font-family:'Vazirmatn','Tahoma',sans-serif;}
  ::selection{background:#bfdbfe;}
</style>
</head>
<body class="bg-ink-50 text-ink-900 min-h-screen flex items-center justify-center p-4">
<div class="w-full max-w-sm">
  <div class="text-center mb-6">
    <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-ink-900 text-white text-2xl font-bold mb-3 shadow-lg shadow-blue-200">ر</div>
    <h1 class="text-xl font-bold text-ink-800">رشن</h1>
    <p class="text-xs text-ink-400 mt-1">زمانِ راست می‌گوید</p>
  </div>
  <div class="bg-white rounded-2xl shadow-sm border border-ink-100 p-6">
    <?= $content ?>
  </div>
</div>
</body>
</html>
