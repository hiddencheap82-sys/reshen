<?php
/**
 * نمای کلی پلتفرم.
 *
 * ترتیب عمدی است: اول چیزی که باید *امروز* کاری برایش کرد (سالن‌های
 * ساکت، صورتحساب معوق)، بعد عددهای کلی. داشبوردی که با عددهای
 * تزئینی شروع شود، هر روز باز می‌شود و هیچ‌وقت به کاری منجر نمی‌شود.
 *
 * @var array $metrics
 * @var array $quiet
 * @var array $invoiceTotals
 * @var array $recent
 */
$active = '';
include __DIR__ . '/_nav.php';

$overdue = $invoiceTotals['overdue'] ?? ['count' => 0, 'total' => 0];
$pending = $invoiceTotals['pending'] ?? ['count' => 0, 'total' => 0];
$paid = $invoiceTotals['paid'] ?? ['count' => 0, 'total' => 0];
?>

<h1 class="page-title mb-4">نمای کلی</h1>

<?php if ($quiet !== [] || $overdue['count'] > 0): ?>
  <!--
    بخش «نیاز به رسیدگی». اگر خالی باشد اصلاً نمایش داده نمی‌شود —
    کارتِ همیشه‌حاضرِ «همه‌چیز خوب است» بعد از یک هفته نادیده گرفته
    می‌شود و آن‌وقت وقتی واقعاً چیزی هست هم دیده نمی‌شود.
  -->
  <section class="glass rounded-2xl p-4 mb-5 hairline-accent">
    <h2 class="card-title mb-3">نیاز به رسیدگی</h2>

    <?php if ($overdue['count'] > 0): ?>
      <a href="<?= e(url('platform/invoices?status=overdue')) ?>"
         class="tap flex items-center gap-3 rounded-xl px-3 py-2.5 mb-2 hover:bg-ink-50">
        <span class="w-9 h-9 shrink-0 rounded-xl grid place-items-center text-red-600"
              style="background:#FEF2F2" aria-hidden="true"><?= icon('wallet', 'w-4 h-4') ?></span>
        <span class="flex-1 min-w-0">
          <span class="block text-[13px] font-bold text-ink-900">
            <?= e(fa_num($overdue['count'])) ?> صورتحساب معوق
          </span>
          <span class="block text-[12px] text-ink-400">
            جمعاً <?= e(App\Support\Money::fromRials((int) $overdue['total'])->formatToman()) ?>
          </span>
        </span>
        <?= icon('chevron-end', 'w-4 h-4 text-ink-300') ?>
      </a>
    <?php endif; ?>

    <?php foreach (array_slice($quiet, 0, 5) as $s): ?>
      <a href="<?= e(url('platform/' . $s['id'])) ?>"
         class="tap flex items-center gap-3 rounded-xl px-3 py-2.5 hover:bg-ink-50">
        <span class="w-9 h-9 shrink-0 rounded-xl grid place-items-center text-amber-600"
              style="background:var(--accent-soft)" aria-hidden="true"><?= icon('alert', 'w-4 h-4') ?></span>
        <span class="flex-1 min-w-0">
          <span class="block text-[13px] font-bold text-ink-900 truncate"><?= e($s['name']) ?></span>
          <span class="block text-[12px] text-ink-400">
            <?php if ($s['last_booking_at'] === null): ?>
              هیچ‌وقت نوبتی ثبت نکرده
            <?php else: ?>
              آخرین نوبت: <?= e(jdate($s['last_booking_at'], 'Y/m/d')) ?>
            <?php endif; ?>
          </span>
        </span>
        <?= icon('chevron-end', 'w-4 h-4 text-ink-300') ?>
      </a>
    <?php endforeach; ?>

    <?php if (count($quiet) > 5): ?>
      <p class="text-[12px] text-ink-400 px-3 pt-2">
        و <?= e(fa_num(count($quiet) - 5)) ?> سالن ساکت دیگر.
      </p>
    <?php endif; ?>
  </section>
<?php endif; ?>

<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
  <?php
  $cards = [
      ['سالن فعال', fa_num((int) ($metrics['active_salons'] ?? 0)), 'text-accent',
       ($metrics['trial_salons'] ?? 0) > 0 ? fa_num((int) $metrics['trial_salons']) . ' در دورهٔ آزمایش' : null],
      ['نوبت هفتهٔ گذشته', fa_num((int) ($metrics['bookings_7d'] ?? 0)), 'text-ink-800',
       fa_num((int) ($metrics['completed_7d'] ?? 0)) . ' به‌سرانجام رسید'],
      ['پیامک ۳۰ روز', fa_num((int) ($metrics['sms_30d'] ?? 0)), 'text-ink-800',
       ($metrics['sms_failed_30d'] ?? 0) > 0
           ? fa_num((int) $metrics['sms_failed_30d']) . ' ناموفق'
           : 'بدون خطا'],
      ['کاربر', fa_num((int) ($metrics['users'] ?? 0)), 'text-ink-800', null],
  ];
  foreach ($cards as [$label, $value, $tone, $sub]):
  ?>
    <div class="glass rounded-2xl p-4">
      <div class="text-2xl font-extrabold tabular-nums <?= $tone ?>"><?= e($value) ?></div>
      <div class="text-[12px] text-ink-500 mt-1"><?= e($label) ?></div>
      <?php if ($sub !== null): ?>
        <div class="text-[12px] text-ink-400 mt-0.5"><?= e($sub) ?></div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>

<!--
  کیفیت محصول، نه کسب‌وکار. MAE یعنی تخمین ما چقدر از واقعیت دور
  است — اگر این خراب شود، کل ادعای محصول («زمانِ راست می‌گوید») خراب
  شده، حتی اگر فروش خوب باشد.
-->
<div class="grid grid-cols-2 gap-3 mb-5">
  <div class="glass rounded-2xl p-4">
    <div class="text-2xl font-extrabold tabular-nums
                <?= $metrics['mae_minutes'] !== null && $metrics['mae_minutes'] > 12 ? 'text-red-500' : 'text-emerald-600' ?>">
      <?= $metrics['mae_minutes'] !== null ? e(fa_num($metrics['mae_minutes'])) : '—' ?>
    </div>
    <div class="text-[12px] text-ink-500 mt-1">خطای تخمین (دقیقه)</div>
    <div class="text-[12px] text-ink-400 mt-0.5">بالای ۱۲ دقیقه یعنی وعده‌ها قابل اتکا نیستند</div>
  </div>
  <div class="glass rounded-2xl p-4">
    <div class="text-2xl font-extrabold tabular-nums
                <?= $metrics['end_registration_rate'] !== null && $metrics['end_registration_rate'] < 70 ? 'text-red-500' : 'text-emerald-600' ?>">
      <?= $metrics['end_registration_rate'] !== null ? e(fa_num($metrics['end_registration_rate'])) . '٪' : '—' ?>
    </div>
    <div class="text-[12px] text-ink-500 mt-1">نرخ ثبت پایان</div>
    <div class="text-[12px] text-ink-400 mt-0.5">اگر پایین باشد، موتور تخمین داده‌ای برای یادگیری ندارد</div>
  </div>
</div>

<section class="glass rounded-2xl p-4 mb-5">
  <div class="flex items-baseline justify-between mb-3">
    <h2 class="card-title">صورتحساب‌ها</h2>
    <a href="<?= e(url('platform/invoices')) ?>"
       class="tap inline-flex items-center h-11 px-2 -me-2 text-[12px] font-bold text-accent">همه</a>
  </div>
  <div class="grid grid-cols-3 gap-3 text-center">
    <?php foreach ([['پرداخت‌شده', $paid, 'text-emerald-600'],
                    ['در انتظار', $pending, 'text-ink-700'],
                    ['معوق', $overdue, 'text-red-600']] as [$label, $bucket, $tone]): ?>
      <div>
        <div class="text-[15px] font-extrabold tabular-nums <?= $tone ?>">
          <?= e(App\Support\Money::fromRials((int) $bucket['total'])->formatToman()) ?>
        </div>
        <div class="text-[12px] text-ink-400 mt-0.5">
          <?= e($label) ?> · <?= e(fa_num((int) $bucket['count'])) ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<?php if ($recent !== []): ?>
  <section class="glass rounded-2xl p-4">
    <div class="flex items-baseline justify-between mb-3">
      <h2 class="card-title">آخرین کارها</h2>
      <a href="<?= e(url('platform/activity')) ?>"
       class="tap inline-flex items-center h-11 px-2 -me-2 text-[12px] font-bold text-accent">همه</a>
    </div>
    <ul class="space-y-2">
      <?php foreach ($recent as $log): ?>
        <li class="flex items-baseline gap-2 text-[12px]">
          <span class="text-ink-400 tabular-nums shrink-0"><?= e(jdate($log['created_at'], 'm/d H:i')) ?></span>
          <span class="text-ink-800 font-semibold"><?= e(App\Domain\Platform\AuditLog::label((string) $log['action'])) ?></span>
          <?php if ($log['salon_name'] !== null): ?>
            <span class="text-ink-400 truncate">— <?= e($log['salon_name']) ?></span>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
<?php endif; ?>
