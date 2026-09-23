<?php
/**
 * پلن‌ها.
 *
 * خواندنی است، نه فرم. قیمت‌ها در مهاجرت ۰۰۱۹ نشسته‌اند و عوض کردنشان
 * از داخل پنل، وسوسهٔ خطرناکی است: یک عدد اشتباه یعنی صورتحساب غلط
 * برای همهٔ سالن‌ها. تا وقتی قیمت‌ها واقعاً تثبیت نشده‌اند، تغییرشان
 * باید آگاهانه و با مهاجرت انجام شود.
 *
 * @var array $plans
 * @var array $counts
 */
$active = 'plans';
include __DIR__ . '/_nav.php';

$features = [
    'has_waitlist' => 'لیست انتظار',
    'has_payout' => 'تسویهٔ صندلی',
    'has_loyalty' => 'باشگاه مشتریان',
    'has_advanced_reports' => 'گزارش پیشرفته',
    'has_multi_branch' => 'چند شعبه',
];
?>

<h1 class="page-title mb-1">پلن‌ها</h1>
<p class="text-[12px] text-ink-400 mb-5 leading-relaxed">
  قیمت‌ها در مهاجرت <span class="code">0019_seed_plans.sql</span> تعریف شده‌اند.
  تغییرشان روی صورتحساب همهٔ سالن‌ها اثر می‌گذارد، پس عمداً از اینجا قابل ویرایش نیست.
</p>

<div class="space-y-3">
  <?php foreach ($plans as $p): ?>
    <div class="glass rounded-2xl p-4">
      <div class="flex items-baseline gap-2 mb-2">
        <h2 class="card-title flex-1 min-w-0"><?= e($p['name']) ?></h2>
        <span class="text-[12px] text-ink-400 code" dir="ltr"><?= e($p['code']) ?></span>
      </div>

      <div class="flex flex-wrap items-baseline gap-x-4 gap-y-1 mb-3">
        <span class="text-lg font-extrabold text-accent tabular-nums">
          <?= (int) $p['monthly_price'] > 0
              ? e(App\Support\Money::fromRials((int) $p['monthly_price'])->formatToman())
              : 'توافقی' ?>
        </span>
        <span class="text-[12px] text-ink-500">
          <?= $p['max_seats'] === null ? 'صندلی نامحدود' : e(fa_num((int) $p['max_seats'])) . ' صندلی' ?>
          <?php if ($p['extra_seat_price'] !== null && (int) $p['extra_seat_price'] > 0): ?>
            · صندلی اضافه <?= e(App\Support\Money::fromRials((int) $p['extra_seat_price'])->formatToman()) ?>
          <?php endif; ?>
        </span>
        <span class="text-[12px] text-ink-500">
          <?= e(fa_num((int) $p['sms_gift_monthly'])) ?> پیامک هدیه
        </span>
        <span class="ms-auto text-[12px] rounded-full px-2 py-0.5"
              style="background:var(--fill-secondary)">
          <?= e(fa_num($counts[$p['code']] ?? 0)) ?> سالن
        </span>
      </div>

      <ul class="flex flex-wrap gap-1.5">
        <?php foreach ($features as $key => $label): ?>
          <li class="text-[12px] rounded-lg px-2 py-1
                     <?= $p[$key] ? 'bg-ink-100 text-ink-700' : 'text-ink-400' ?>">
            <?= $p[$key] ? '✓' : '—' ?> <?= e($label) ?>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endforeach; ?>
</div>
