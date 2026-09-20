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
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($title ?? 'رشن') ?></title>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<link rel="manifest" href="<?= e(url('manifest.webmanifest')) ?>">
<link rel="icon" href="<?= e(asset('icons/icon.svg')) ?>" type="image/svg+xml">
<meta name="theme-color" content="#1C1917">
</head>
<body class="min-h-dvh">

<div class="max-w-md mx-auto min-h-dvh flex flex-col shadow-deep"
     style="background:var(--surface)">

  <?php if (!empty($salon)): ?>
    <!--
      سرصفحه. بافت مورب و درخشش طلایی، عمق می‌دهد بدون اینکه عکس لازم
      باشد — سالنی که هنوز لوگو آپلود نکرده هم باید آبرومند به نظر برسد.
    -->
    <header class="on-dark relative overflow-hidden text-white px-5 pt-7 pb-6"
            style="background:linear-gradient(145deg,#1C1917 0%,#292524 55%,#1C1917 100%)">

      <div class="absolute inset-0 opacity-[0.07]" aria-hidden="true"
           style="background-image:repeating-linear-gradient(135deg,#fff 0 1px,transparent 1px 9px)"></div>
      <div class="absolute -top-16 -left-10 w-48 h-48 rounded-full blur-3xl opacity-25" aria-hidden="true"
           style="background:radial-gradient(circle,#A16207 0%,transparent 70%)"></div>

      <div class="relative">
        <div class="flex items-center gap-2 mb-3">
          <span class="w-7 h-7 rounded-lg grid place-items-center text-[13px] font-extrabold text-ink-950"
                style="background:linear-gradient(180deg,#FCD34D,#A16207)" aria-hidden="true">ر</span>
          <span class="text-[11px] tracking-wide text-ink-300">رشن</span>
        </div>

        <h1 class="text-xl font-extrabold leading-tight"><?= e($salon['name']) ?></h1>

        <?php if (!empty($salon['address']) || !empty($salon['city'])): ?>
          <p class="flex items-start gap-1.5 text-[12.5px] text-ink-300 mt-2 leading-relaxed">
            <svg class="w-3.5 h-3.5 mt-0.5 shrink-0 text-gold-400" fill="none" viewBox="0 0 24 24"
                 stroke="currentColor" stroke-width="2" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round"
                    d="M17.657 16.657L13.414 20.9a2 2 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            <span><?= e(trim(($salon['city'] ?? '') . ' · ' . ($salon['address'] ?? ''), ' ·')) ?></span>
          </p>
        <?php endif; ?>

        <?php if (!empty($salon['phone'])): ?>
          <a href="tel:<?= e($salon['phone']) ?>"
             class="inline-flex items-center gap-1.5 mt-3 h-9 px-3 rounded-lg text-[12.5px] font-semibold
                    bg-white/10 hover:bg-white/15 text-white transition-colors tap
                    focus-visible:outline-2 focus-visible:outline-gold-400">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                 stroke-width="2" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round"
                    d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11 11 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
            </svg>
            <span class="ltr tabular-nums"><?= e(fa_num($salon['phone'])) ?></span>
          </a>
        <?php endif; ?>
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
                           <?= $done ? 'bg-gold-600' : ($now ? 'bg-ink-900' : 'bg-ink-200') ?>"></span>
              <span class="text-[10.5px] font-semibold text-center
                           <?= $now ? 'text-ink-900' : ($done ? 'text-gold-700' : 'text-ink-400') ?>">
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

</body>
</html>
