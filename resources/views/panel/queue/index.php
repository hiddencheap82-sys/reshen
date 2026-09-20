<?php
/** @var array $snapshot
 * @var ?int $myStaffId
 * @var array $services
 * @var array $staffList
 * @var int $todayCount
 * @var array $todaySummary
 * @var ?array $salonEarnings
 */
use App\Core\Auth;
use App\Support\JalaliCalendar;

$role = Auth::role();
$today = new DateTimeImmutable('today');
$sum = $todaySummary ?? ['total'=>0,'completed'=>0,'waiting'=>0,'in_chair'=>0,'no_show'=>0];
?>
<script>setTimeout(() => location.reload(), 15000);</script>

<!--
  نوار خلاصهٔ امروز.

  چرا تاریخ شمسی اینجاست: صاحب سالن روز را با تاریخ شمسی می‌شناسد، و
  «پنج‌شنبه» برایش معنای عملیاتی دارد (شلوغ‌ترین روز هفته). تاریخ میلادی
  یا نبودِ تاریخ، این صفحه را از واقعیت جدا می‌کند.
-->
<div class="bg-white rounded-2xl border border-slate-100 p-4 mb-4">
  <div class="flex items-baseline justify-between mb-3">
    <div>
      <div class="text-sm font-bold text-slate-800">
        <?= e(JalaliCalendar::humanDate($today, true)) ?>
      </div>
      <?php if (App\Support\Jalali::isWeekend($today)): ?>
        <div class="text-[11px] text-rose-500 font-semibold mt-0.5">آخر هفته — روز شلوغ</div>
      <?php endif; ?>
    </div>
    <?php if ($salonEarnings !== null): ?>
      <div class="text-left">
        <div class="text-[11px] text-slate-400">فروش امروز</div>
        <div class="text-lg font-extrabold text-brand-700 tabular-nums">
          <?= e(toman((int) ($salonEarnings['total'] ?? 0))) ?>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <dl class="grid grid-cols-4 gap-2">
    <?php foreach ([
      ['در انتظار', $sum['waiting'],   'text-amber-600', 'bg-amber-50'],
      ['روی صندلی', $sum['in_chair'],  'text-brand-700', 'bg-brand-50'],
      ['انجام‌شده', $sum['completed'], 'text-green-700', 'bg-green-50'],
      ['غیبت',      $sum['no_show'],   'text-slate-500', 'bg-slate-50'],
    ] as [$label, $value, $fg, $bg]): ?>
      <div class="<?= $bg ?> rounded-xl py-2 px-1 text-center">
        <dd class="text-xl font-extrabold <?= $fg ?> tabular-nums"><?= e(fa_num((int) $value)) ?></dd>
        <dt class="text-[11px] text-slate-500 mt-0.5"><?= e($label) ?></dt>
      </div>
    <?php endforeach; ?>
  </dl>
</div>

<div class="flex items-center justify-between mb-4">
  <h1 class="text-lg font-bold text-slate-800">صف زنده</h1>
  <?php if (in_array($role, ['owner','manager','reception'], true)): ?>
  <button onclick="document.getElementById('walkin-box').classList.toggle('hidden')" class="bg-brand-600 hover:bg-brand-700 text-white text-sm font-bold rounded-xl px-4 py-2.5">+ افزودن حضوری</button>
  <?php endif; ?>
</div>

<div id="walkin-box" class="hidden bg-white rounded-2xl border border-slate-100 p-5 mb-5">
  <h2 class="text-sm font-bold text-slate-700 mb-3">افزودن مراجعهٔ حضوری</h2>
  <form method="post" action="<?= url('panel/queue/walkin') ?>" class="space-y-3">
    <?= csrf_field() ?>
    <div class="grid sm:grid-cols-2 gap-3">
      <input type="text" name="name" placeholder="نام مشتری (اختیاری)" class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm">
      <input type="tel" name="phone" dir="ltr" placeholder="شمارهٔ موبایل (اختیاری)" class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm text-left">
    </div>
    <div>
      <label class="block text-xs text-slate-500 mb-1.5">خدمت(ها)</label>
      <div class="flex flex-wrap gap-2">
        <?php foreach ($services as $s): ?>
        <label class="flex items-center gap-1.5 text-xs bg-slate-50 border border-slate-200 rounded-lg px-2.5 py-1.5 cursor-pointer">
          <input type="checkbox" name="service_ids[]" value="<?= (int)$s['id'] ?>"><?= e($s['name']) ?>
        </label>
        <?php endforeach; ?>
      </div>
    </div>
    <div>
      <label class="block text-xs text-slate-500 mb-1.5">آرایشگر</label>
      <select name="staff_id" class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm">
        <option value="">فرقی نمی‌کند (کمترین صف)</option>
        <?php foreach ($staffList as $st): ?>
        <option value="<?= (int)$st['id'] ?>"><?= e($st['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white text-sm font-bold rounded-xl px-5 py-2.5">افزودن به صف</button>
  </form>
</div>

<?php if ($role === 'staff' && $myStaffId !== null): ?>
  <?php $mine = null; foreach ($snapshot as $g) { if ((int)$g['staff']['id'] === $myStaffId) { $mine = $g; } } ?>
  <div class="bg-white rounded-2xl border border-slate-100 p-5 mb-5 text-center">
    <div class="text-xs text-slate-400 mb-1">امروز تو چقدر درآوردی</div>
    <div class="text-3xl font-extrabold text-brand-700"><?= toman((int)($todayEarnings['total'] ?? 0)) ?></div>
    <div class="text-xs text-slate-400 mt-1"><?= fa_num($todayCount) ?> نوبت انجام‌شده</div>
  </div>

  <?php if ($mine && !empty($mine['queue'])): $current = $mine['queue'][0]; ?>
    <?php if ($current['status'] === 'in_chair'): ?>
      <div class="bg-white rounded-2xl border border-slate-100 p-5 mb-3">
        <div class="text-xs text-slate-400 mb-1">روی صندلی</div>
        <div class="text-xl font-bold text-slate-800"><?= e($current['customer_name'] ?: 'مشتری') ?></div>
        <div class="text-xs text-slate-400 mt-1"><?= e(implode('، ', array_column($current['items'], 'service_name'))) ?></div>
      </div>
      <form method="post" action="<?= url('panel/queue/' . $current['id'] . '/complete') ?>">
        <?= csrf_field() ?>
        <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-xl rounded-2xl py-8 shadow-lg shadow-emerald-100">تمام شد</button>
      </form>
    <?php else: ?>
      <div class="bg-white rounded-2xl border border-slate-100 p-5 mb-3">
        <div class="text-xs text-slate-400 mb-1">نفر بعدی</div>
        <div class="text-xl font-bold text-slate-800"><?= e($current['customer_name'] ?: 'مشتری') ?></div>
        <div class="text-xs text-slate-400 mt-1"><?= e(implode('، ', array_column($current['items'], 'service_name'))) ?></div>
      </div>
      <form method="post" action="<?= url('panel/queue/' . $current['id'] . '/start') ?>">
        <?= csrf_field() ?>
        <button type="submit" class="w-full bg-brand-600 hover:bg-brand-700 text-white font-extrabold text-xl rounded-2xl py-8 shadow-lg shadow-blue-100">شروع</button>
      </form>
    <?php endif; ?>

    <?php if (count($mine['queue']) > 1): ?>
    <div class="mt-5">
      <h3 class="text-xs font-bold text-slate-400 mb-2">در صف</h3>
      <div class="space-y-2">
        <?php foreach (array_slice($mine['queue'], 1) as $row): ?>
        <div class="bg-white rounded-xl border border-slate-100 px-4 py-3 flex items-center justify-between">
          <span class="text-sm text-slate-700"><?= e($row['customer_name'] ?: 'مشتری') ?></span>
          <span class="text-xs text-slate-400"><?= e($row['display']['text'] ?? '') ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  <?php else: ?>
    <div class="bg-white rounded-2xl border border-dashed border-slate-200 p-10 text-center text-slate-400">صف شما خالی است.</div>
  <?php endif; ?>

<?php else: ?>
  <div class="grid md:grid-cols-2 xl:grid-cols-3 gap-4">
    <?php foreach ($snapshot as $group): ?>
    <div class="bg-white rounded-2xl border border-slate-100 overflow-hidden">
      <div class="px-4 py-3 border-b border-slate-100 flex items-center gap-2" style="background:<?= e($group['staff']['color']) ?>10">
        <span class="w-2.5 h-2.5 rounded-full" style="background:<?= e($group['staff']['color']) ?>"></span>
        <span class="font-bold text-sm text-slate-800"><?= e($group['staff']['name']) ?></span>
        <span class="text-xs text-slate-400 mr-auto"><?= fa_num(count($group['queue'])) ?> نفر</span>
      </div>
      <div class="divide-y divide-slate-100">
        <?php if (empty($group['queue'])): ?>
          <div class="px-4 py-6 text-center text-xs text-slate-400">صف خالی</div>
        <?php endif; ?>
        <?php foreach ($group['queue'] as $row): ?>
        <div class="px-4 py-3">
          <div class="flex items-center justify-between">
            <div>
              <div class="text-sm font-bold text-slate-800 flex items-center gap-1.5">
                <?php if ($row['status']==='in_chair'): ?><span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span><?php endif; ?>
                <?= e($row['customer_name'] ?: 'مشتری') ?>
                <?= $row['kind']==='booked' ? '<span class="text-[10px] bg-blue-50 text-blue-600 rounded px-1.5 py-0.5">رزرو</span>' : '' ?>
              </div>
              <div class="text-[11px] text-slate-400"><?= e(implode('، ', array_column($row['items'], 'service_name'))) ?></div>
            </div>
            <div class="text-xs font-bold <?= $row['status']==='in_chair' ? 'text-emerald-600' : 'text-slate-500' ?>">
              <?= e($row['display']['text'] ?? '') ?>
            </div>
          </div>
          <div class="flex gap-1.5 mt-2">
            <?php if ($row['status'] !== 'in_chair'): ?>
            <form method="post" action="<?= url('panel/queue/' . $row['id'] . '/start') ?>">
              <?= csrf_field() ?>
              <button class="text-[11px] bg-brand-50 text-brand-700 hover:bg-brand-100 rounded-lg px-2.5 py-1">شروع</button>
            </form>
            <?php else: ?>
            <form method="post" action="<?= url('panel/queue/' . $row['id'] . '/complete') ?>">
              <?= csrf_field() ?>
              <button class="text-[11px] bg-emerald-50 text-emerald-700 hover:bg-emerald-100 rounded-lg px-2.5 py-1">تمام شد</button>
            </form>
            <?php endif; ?>
            <form method="post" action="<?= url('panel/queue/' . $row['id'] . '/no-show') ?>">
              <?= csrf_field() ?>
              <button class="text-[11px] bg-slate-50 text-slate-500 hover:bg-slate-100 rounded-lg px-2.5 py-1">غیبت</button>
            </form>
            <form method="post" action="<?= url('panel/queue/' . $row['id'] . '/cancel') ?>">
              <?= csrf_field() ?>
              <button class="text-[11px] bg-red-50 text-red-500 hover:bg-red-100 rounded-lg px-2.5 py-1">لغو</button>
            </form>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
