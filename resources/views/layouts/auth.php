<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($title ?? 'رشن') ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<script>
  tailwind.config = {
    theme: { extend: {
      fontFamily: { sans: ['Vazirmatn','Tahoma','sans-serif'] },
      colors: { brand: { 50:'#eff6ff',100:'#dbeafe',500:'#2563eb',600:'#1d4ed8',700:'#1e40af',900:'#1e3a8a' } }
    } }
  }
</script>
<link rel="manifest" href="<?= url('manifest.webmanifest') ?>">
<link rel="icon" href="<?= asset('icons/icon.svg') ?>" type="image/svg+xml">
<meta name="theme-color" content="#1d4ed8">
<style>
  body{font-family:'Vazirmatn','Tahoma',sans-serif;}
  ::selection{background:#bfdbfe;}
</style>
</head>
<body class="bg-slate-50 text-slate-900 min-h-screen flex items-center justify-center p-4">
<div class="w-full max-w-sm">
  <div class="text-center mb-6">
    <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-brand-600 text-white text-2xl font-bold mb-3 shadow-lg shadow-blue-200">ر</div>
    <h1 class="text-xl font-bold text-slate-800">رشن</h1>
    <p class="text-xs text-slate-400 mt-1">زمانِ راست می‌گوید</p>
  </div>
  <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
    <?= $content ?>
  </div>
</div>
</body>
</html>
