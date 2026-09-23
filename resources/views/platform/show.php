<?php
/**
 * پروندهٔ یک سالن از دید پلتفرم.
 *
 * @var array $salon
 * @var array $owners
 * @var array $plans
 * @var int   $monthlyPrice
 * @var array $invoices
 * @var array $auditLogs
 */
$roleNames = ['owner' => 'صاحب', 'manager' => 'مدیر', 'staff' => 'آرایشگر', 'reception' => 'پذیرش'];
$statusNames = [
    'pending' => ['در انتظار', 'text-ink-600', 'var(--fill-secondary)'],
    'paid' => ['پرداخت شد', 'text-ok', 'var(--ok-soft)'],
    'overdue' => ['معوق', 'text-bad', 'var(--bad-soft)'],
    'cancelled' => ['لغو شد', 'text-ink-400', 'var(--fill-secondary)'],
];
$trialEnds = $salon['trial_ends_at'];
?>

<div class="flex items-center gap-2 mb-4">
  <a href="<?= e(url('platform/salons')) ?>"
     class="tap w-11 h-11 min-w-[44px] -ms-2 grid place-items-center rounded-xl text-ink-400"
     aria-label="بازگشت به فهرست سالن‌ها"><?= icon('chevron-start', 'w-5 h-5') ?></a>
  <h1 class="page-title flex-1 min-w-0 truncate"><?= e($salon['name']) ?></h1>
  <?php if (!$salon['is_active']): ?>
    <span class="text-[12px] rounded-full px-2.5 py-1 bg-ink-100 text-ink-500 shrink-0">غیرفعال</span>
  <?php endif; ?>
</div>

<div class="grid lg:grid-cols-2 gap-4">

  <section class="glass rounded-2xl p-4">
    <h2 class="card-title mb-3">سالن</h2>
    <dl class="text-[13px] space-y-1.5 text-ink-600">
      <div class="flex gap-2"><dt class="text-ink-400 w-24 shrink-0">نشانی عمومی</dt>
        <dd class="min-w-0"><a href="<?= e(url('s/' . $salon['slug'])) ?>" class="code text-accent truncate block" dir="ltr">/s/<?= e($salon['slug']) ?></a></dd></div>
      <div class="flex gap-2"><dt class="text-ink-400 w-24 shrink-0">شهر</dt><dd><?= e($salon['city'] ?? '—') ?></dd></div>
      <div class="flex gap-2"><dt class="text-ink-400 w-24 shrink-0">ثبت‌نام</dt><dd class="tabular-nums"><?= e(jdate($salon['created_at'], 'Y/m/d')) ?></dd></div>
      <div class="flex gap-2"><dt class="text-ink-400 w-24 shrink-0">اجارهٔ ماهانه</dt>
        <dd class="font-bold text-ink-800"><?= e(App\Support\Money::fromRials($monthlyPrice)->formatToman()) ?></dd></div>
    </dl>

    <div class="mt-4 pt-4 space-y-2" style="border-top:1px solid var(--line)">
      <form method="post" action="<?= e(url('platform/' . $salon['id'] . '/impersonate')) ?>">
        <?= csrf_field() ?>
        <!--
          ورود به‌جای صاحب سالن. رنگش عمداً هشداری است و نه رنگ پالت:
          این کار یعنی دیدن دادهٔ مشتری‌های یک کسب‌وکار دیگر، و باید
          حس کند که کار عادی‌ای نیست. در گزارش فعالیت هم ثبت می‌شود.
        -->
        <button class="btn-ink w-full h-11 text-[13px]">ورود پشتیبانی به پنل این سالن</button>
      </form>

      <form method="post" action="<?= e(url('platform/' . $salon['id'] . '/active')) ?>">
        <?= csrf_field() ?>
        <button class="tap w-full h-11 rounded-xl text-[13px] font-bold
                       <?= $salon['is_active'] ? 'text-bad hover:bg-bad-soft' : 'text-ok hover:bg-ok-soft' ?>">
          <?= $salon['is_active'] ? 'غیرفعال کردن سالن' : 'فعال کردن سالن' ?>
        </button>
      </form>
      <p class="text-[12px] text-ink-400 leading-relaxed">
        غیرفعال کردن، صفحهٔ عمومی رزرو را می‌بندد. دادهٔ سالن و مشتری‌هایش دست‌نخورده می‌ماند.
      </p>
    </div>
  </section>

  <section class="glass rounded-2xl p-4">
    <h2 class="card-title mb-3">پلن و صندلی</h2>

    <form method="post" action="<?= e(url('platform/' . $salon['id'] . '/plan')) ?>" class="space-y-3">
      <?= csrf_field() ?>

      <div>
        <label for="plan-code" class="block text-[13px] font-semibold text-ink-800 mb-1.5">پلن</label>
        <select id="plan-code" name="plan_code"
                class="field">
          <?php foreach ($plans as $p): ?>
            <option value="<?= e($p['code']) ?>" <?= $p['code'] === $salon['plan_code'] ? 'selected' : '' ?>>
              <?= e($p['name']) ?>
              <?php if ((int) $p['monthly_price'] > 0): ?>
                — <?= e(App\Support\Money::fromRials((int) $p['monthly_price'])->formatToman()) ?>
              <?php endif; ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label for="plan-seats" class="block text-[13px] font-semibold text-ink-800 mb-1.5">تعداد صندلی</label>
        <input type="number" inputmode="numeric" id="plan-seats" name="seats" min="1" max="99"
               value="<?= e((string) $salon['seats']) ?>"
               class="field tabular-nums">
      </div>

      <div>
        <span class="block text-[13px] font-semibold text-ink-800 mb-1.5">
          پایان دورهٔ آزمایش
        </span>
        <!--
          تقویم شمسی، نه <input type="date">. آن یکی تقویم میلادی باز
          می‌کند و «10/23/2026» می‌نویسد — در برنامه‌ای که همه‌جایش
          شمسی است، مدیر باید در ذهنش تبدیل کند.
        -->
        <?= App\Core\View::render('components.jalali-date-input', [
            'name' => 'trial_ends',
            'label' => 'پایان دورهٔ آزمایش',
            'value' => $trialEnds !== null ? substr((string) $trialEnds, 0, 10) : null,
            'years' => [-1, 3],
        ]) ?>
        <label class="tap flex items-center gap-2 mt-2 text-[12px] text-ink-500">
          <input type="checkbox" name="no_trial_end" value="1" class="w-4 h-4 accent-current"
                 <?= $trialEnds === null ? 'checked' : '' ?>>
          بدون مهلت
        </label>
        <?php if ($salon['plan_code'] === 'trial' && $trialEnds === null): ?>
          <p class="text-[12px] text-warn mt-1.5">
            این سالن در پلن آزمایشی است و مهلتی ندارد — یعنی برای همیشه رایگان.
          </p>
        <?php endif; ?>
      </div>

      <button type="submit" class="btn-accent w-full h-11 text-[13px]">ذخیرهٔ پلن</button>
    </form>
  </section>

  <section class="glass rounded-2xl p-4">
    <div class="flex items-baseline justify-between mb-3">
      <h2 class="card-title">صورتحساب‌ها</h2>
      <form method="post" action="<?= e(url('platform/' . $salon['id'] . '/invoice')) ?>">
        <?= csrf_field() ?>
        <button class="tap h-11 px-3 rounded-lg text-[12px] font-bold text-accent hover:bg-ink-100">
          + صدور این ماه
        </button>
      </form>
    </div>

    <?php if ($invoices === []): ?>
      <p class="text-[12px] text-ink-400">هنوز صورتحسابی صادر نشده.</p>
    <?php else: ?>
      <ul class="space-y-2">
        <?php foreach ($invoices as $inv): ?>
          <?php [$label, $tone, $bg] = $statusNames[$inv['status']] ?? ['—', 'text-ink-500', 'var(--fill-secondary)']; ?>
          <li class="flex items-center gap-2 text-[13px]">
            <span class="tabular-nums text-ink-400 shrink-0"><?= e(jdate($inv['period_start'], 'Y/m')) ?></span>
            <span class="flex-1 font-bold text-ink-800 tabular-nums">
              <?= e(App\Support\Money::fromRials((int) $inv['amount'])->formatToman()) ?>
            </span>
            <span class="text-[12px] rounded-full px-2 py-0.5 shrink-0 <?= $tone ?>"
                  style="background:<?= $bg ?>"><?= e($label) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>

  <section class="glass rounded-2xl p-4">
    <h2 class="card-title mb-3">اعضا</h2>
    <?php if ($owners === []): ?>
      <p class="text-[12px] text-ink-400">هیچ کاربری به این سالن وصل نیست.</p>
    <?php else: ?>
      <ul class="space-y-1.5">
        <?php foreach ($owners as $o): ?>
          <li class="flex items-center justify-between text-[13px]">
            <span class="min-w-0 truncate">
              <?= e($o['name'] ?? '—') ?>
              <span dir="ltr" class="text-ink-400 code"><?= e(fa_num($o['phone'])) ?></span>
            </span>
            <span class="text-[12px] text-ink-400 shrink-0"><?= e($roleNames[$o['role']] ?? $o['role']) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>

  <section class="glass rounded-2xl p-4 lg:col-span-2">
    <h2 class="card-title mb-3">فعالیت این سالن</h2>
    <?php if ($auditLogs === []): ?>
      <p class="text-[12px] text-ink-400">رویدادی ثبت نشده.</p>
    <?php else: ?>
      <ul class="space-y-2">
        <?php foreach ($auditLogs as $log): ?>
          <li class="flex flex-wrap items-baseline gap-x-2 text-[12px] pb-1.5"
              style="border-bottom:1px solid var(--line)">
            <span class="text-ink-400 tabular-nums shrink-0"><?= e(jdate($log['created_at'], 'Y/m/d H:i')) ?></span>
            <span class="font-bold text-ink-800"><?= e(App\Domain\Platform\AuditLog::label((string) $log['action'])) ?></span>
            <span class="text-ink-400" dir="ltr"><?= e(fa_num($log['actor_phone'] ?? 'سیستم')) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>

</div>
