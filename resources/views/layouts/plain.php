<?php
/**
 * قالب صفحه‌های مستقل — بدون پنل، بدون سالن.
 *
 * تفاوتش با `auth`: آن یکی محتوا را داخل یک کارت باریک می‌گذارد، که
 * برای فرم ورود درست است ولی برای صفحه‌ای که چند بخش دارد تنگ است.
 * اینجا فقط سرصفحه و حاشیه را می‌دهد و چیدمان با خود صفحه است.
 */
?>
<!doctype html>
<html lang="fa" dir="rtl" data-font="<?= e((string) App\Core\Config::get('reshen.ui.font', 'vazirmatn')) ?>" <?= theme_attr(null) ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($title ?? 'رشن') ?></title>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<?php include BASE_PATH . '/resources/views/components/pwa-head.php'; ?>
<meta name="color-scheme" content="light dark">
<?php include BASE_PATH . '/resources/views/components/theme-boot.php'; ?>
</head>
<body class="min-h-dvh">

<?php include BASE_PATH . '/resources/views/components/icons.svg'; ?>

<div class="max-w-md mx-auto min-h-dvh flex flex-col px-5 py-6">
  <div class="flex items-center gap-2 mb-6">
    <span class="w-10 h-10 rounded-xl grid place-items-center text-lg font-extrabold metal shrink-0"
          style="color:var(--on-accent)" aria-hidden="true">ر</span>
    <span>
      <span class="block text-[15px] font-extrabold text-ink-900 leading-tight">رشن</span>
      <span class="block text-[12px] text-ink-400 leading-tight">زمانِ راست می‌گوید</span>
    </span>
    <span class="ms-auto">
      <?php include BASE_PATH . '/resources/views/components/theme-toggle.php'; ?>
    </span>
  </div>

  <main class="flex-1"><?php include BASE_PATH . '/resources/views/components/flash.php'; ?>
<?= $content ?></main>
</div>
</body>
</html>
