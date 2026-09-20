<?php
/**
 * قالب صفحات مشتری.
 *
 * $step / $steps اختیاری‌اند؛ اگر باشند نوار پیشرفت بالای صفحه می‌آید.
 * مشتری باید بداند در کدام مرحله است و چند مرحله مانده — وگرنه وسط کار
 * رها می‌کند.
 */
$stepTitles = ['خدمت', 'آرایشگر', 'زمان', 'تأیید'];
$step = $step ?? null;
?>
<!doctype html>
<html lang="fa" dir="rtl" data-font="<?= e((string) App\Core\Config::get('reshen.ui.font', 'vazirmatn')) ?>" <?= theme_attr($salon['theme'] ?? null) ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($title ?? 'رشن') ?></title>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<?php
  $pwaSalonSlug = $salon['slug'] ?? null;
  $pwaAppTitle = $salon['name'] ?? 'رشن';
  include BASE_PATH . '/resources/views/components/pwa-head.php';
?>
<meta name="theme-color" content="#1C1917" id="theme-color">
<meta name="color-scheme" content="light dark">
<?php include BASE_PATH . '/resources/views/components/theme-boot.php'; ?>
</head>
<body class="min-h-dvh">

<?php include BASE_PATH . '/resources/views/components/icons.svg'; ?>

<div class="max-w-md mx-auto min-h-dvh flex flex-col shadow-deep"
     style="background:var(--surface)">

  <?php if (!empty($salon)): ?>
    <!--
      سرصفحه. بافت مورب و درخشش طلایی، عمق می‌دهد بدون اینکه عکس لازم
      باشد — سالنی که هنوز لوگو آپلود نکرده هم باید آبرومند به نظر برسد.
    -->
    <header class="on-dark relative overflow-hidden text-white px-5 pt-7 pb-6"
            style="background:var(--hero)">

      <div class="absolute inset-0 opacity-[0.07]" aria-hidden="true"
           style="background-image:repeating-linear-gradient(135deg,#fff 0 1px,transparent 1px 9px)"></div>
      <div class="absolute -top-16 -left-10 w-48 h-48 rounded-full blur-3xl opacity-25" aria-hidden="true"
           style="background:radial-gradient(circle,var(--accent) 0%,transparent 70%)"></div>

      <div class="relative">
        <div class="flex items-center gap-2 mb-3">
          <span class="w-7 h-7 rounded-lg grid place-items-center text-[13px] font-extrabold text-ink-950"
                style="background:linear-gradient(160deg,color-mix(in srgb,var(--accent) 55%,white),var(--accent))" aria-hidden="true">ر</span>
          <span class="text-[11px] tracking-wide text-ink-300">رشن</span>

          <!-- کلید روشن/تیره در انتهای همان ردیف، دور از دکمه‌های رزرو -->
          <span class="ms-auto -my-2 -me-2">
            <?php $toggleTone = 'on-dark'; include BASE_PATH . '/resources/views/components/theme-toggle.php'; ?>
          </span>
        </div>

        <h1 class="text-xl font-extrabold leading-tight"><?= e($salon['name']) ?></h1>

        <?php if (!empty($salon['address']) || !empty($salon['city'])): ?>
          <p class="flex items-start gap-1.5 text-[12px] text-ink-300 mt-2 leading-relaxed">
            <?= icon('map-pin', 'w-3.5 h-3.5 mt-0.5 shrink-0 opacity-80') ?>
            <span><?= e(trim(($salon['city'] ?? '') . ' · ' . ($salon['address'] ?? ''), ' ·')) ?></span>
          </p>
        <?php endif; ?>

        <div class="flex flex-wrap items-center gap-2 mt-3">
          <?php if (!empty($salon['phone'])): ?>
            <a href="tel:<?= e($salon['phone']) ?>"
               class="inline-flex items-center gap-1.5 h-11 px-3.5 rounded-xl text-[12px] font-semibold
                      bg-white/10 hover:bg-white/15 text-white transition-colors tap
                      focus-visible:outline-2 focus-visible:outline-accent">
              <?= icon('phone', 'w-3.5 h-3.5') ?>
              <span class="ltr tabular-nums"><?= e(fa_num($salon['phone'])) ?></span>
            </a>
          <?php endif; ?>

          <!--
            منوی خدمات. خیلی‌ها لینک را از اینستاگرام باز می‌کنند و اول
            فقط می‌خواهند بدانند «چی دارید و چند؟» — بدون این دکمه،
            جوابِ آن سؤال پشتِ جریان رزرو پنهان می‌ماند.
          -->
          <a href="<?= e(url('s/' . $salon['slug'] . '/services')) ?>"
             class="inline-flex items-center gap-1.5 h-11 px-3.5 rounded-xl text-[12px] font-semibold
                    bg-white/10 hover:bg-white/15 text-white transition-colors tap
                    focus-visible:outline-2 focus-visible:outline-accent">
            <?= icon('tag', 'w-3.5 h-3.5') ?>
            خدمات و قیمت‌ها
          </a>
        </div>
      </div>
    </header>

    <?php if ($step !== null): ?>
      <!-- نوار پیشرفت: کجای کاریم و چند قدم مانده -->
      <nav class="px-5 py-3.5 border-b" style="border-color:var(--line)"
           aria-label="مراحل رزرو">
        <ol class="flex items-center gap-1.5">
          <?php foreach ($stepTitles as $i => $label):
              $n = $i + 1;
              $done = $n < $step;
              $now  = $n === $step;
          ?>
            <li class="flex-1 flex flex-col gap-1.5"
                <?= $now ? 'aria-current="step"' : '' ?>>
              <span class="h-1 rounded-full transition-colors duration-300
                           <?= $done ? 'bg-accent' : ($now ? 'bg-ink-900' : 'bg-ink-200') ?>"></span>
              <span class="text-[11px] font-semibold text-center
                           <?= $now ? 'text-ink-900' : ($done ? 'text-accent' : 'text-ink-400') ?>">
                <?= e($label) ?>
              </span>
            </li>
          <?php endforeach; ?>
        </ol>
      </nav>
    <?php endif; ?>
  <?php endif; ?>

  <main class="flex-1 px-5 py-5">
    <?= $content ?>
  </main>

  <footer class="text-center pb-5 pt-2">
    <span class="text-[11px] text-ink-300">رشن — زمانِ راست می‌گوید</span>
  </footer>
</div>

<?php include BASE_PATH . '/resources/views/components/install-prompt.php'; ?>

</body>
</html>
