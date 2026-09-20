<?php
/**
 * قالب ورود و ثبت‌نام.
 *
 * تا پیش از ورود، سالنی در کار نیست که پالتش را بدهد — پس پالت پیش‌فرض
 * می‌نشیند. ولی روشن/تیره همین‌جا هم باید در دسترس باشد: کسی که شب
 * وارد می‌شود، نباید اول یک صفحهٔ سفید بخورد توی صورتش.
 */
?>
<!doctype html>
<html lang="fa" dir="rtl" data-font="<?= e((string) App\Core\Config::get('reshen.ui.font', 'vazirmatn')) ?>" <?= theme_attr(null) ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($title ?? 'رشن') ?></title>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<link rel="manifest" href="<?= e(url('manifest.webmanifest')) ?>">
<link rel="icon" href="<?= e(asset('icons/icon.svg')) ?>" type="image/svg+xml">
<meta name="color-scheme" content="light dark">
<?php include BASE_PATH . '/resources/views/components/theme-boot.php'; ?>
</head>
<body class="min-h-dvh flex items-center justify-center p-4">

<?php include BASE_PATH . '/resources/views/components/icons.svg'; ?>

<div class="absolute top-3 left-3">
  <?php include BASE_PATH . '/resources/views/components/theme-toggle.php'; ?>
</div>

<div class="w-full max-w-sm">
  <div class="text-center mb-6">
    <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl mb-3 metal metal-ink
                text-white text-2xl font-extrabold shadow-deep">ر</div>
    <h1 class="page-title">رشن</h1>
    <p class="text-xs text-ink-400 mt-1">زمانِ راست می‌گوید</p>
  </div>
  <div class="glass rounded-2xl p-6">
    <?= $content ?>
  </div>
</div>
</body>
</html>
