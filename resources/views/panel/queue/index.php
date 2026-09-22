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
<div class="glass rounded-2xl p-4 mb-4 rise hairline-accent">
  <div class="flex items-baseline justify-between mb-3">
    <div>
      <div class="text-[15px] font-extrabold text-ink-900">
        <?= e(JalaliCalendar::humanDate($today, true)) ?>
      </div>
      <?php if (App\Support\Jalali::isWeekend($today)): ?>
        <div class="text-[12px] text-accent font-bold mt-0.5">آخر هفته — روز شلوغ</div>
      <?php endif; ?>
    </div>
    <?php if ($salonEarnings !== null): ?>
      <div class="text-left">
        <div class="text-[12px] text-ink-400">فروش امروز</div>
        <div class="text-xl font-extrabold text-accent tabular-nums">
          <?= e(toman((int) ($salonEarnings['total'] ?? 0))) ?>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <dl class="grid grid-cols-4 gap-2">
    <?php foreach ([
      ['در انتظار', $sum['waiting'],   'text-accent', 'bg-gold-50'],
      ['روی صندلی', $sum['in_chair'],  'text-ink-900', 'bg-ink-100'],
      ['انجام‌شده', $sum['completed'], 'text-green-700', 'bg-green-50'],
      ['غیبت',      $sum['no_show'],   'text-ink-500', 'bg-ink-50'],
    ] as [$label, $value, $fg, $bg]): ?>
      <div class="<?= $bg ?> rounded-xl py-2 px-1 text-center">
        <dd class="text-xl font-extrabold <?= $fg ?> tabular-nums"><?= e(fa_num((int) $value)) ?></dd>
        <dt class="text-[12px] text-ink-500 mt-0.5"><?= e($label) ?></dt>
      </div>
    <?php endforeach; ?>
  </dl>
</div>

<div class="flex items-center justify-between mb-4">
  <h1 class="page-title">صف زنده</h1>
  <?php if (in_array($role, ['owner','manager','reception'], true)): ?>
  <button onclick="document.getElementById('walkin-box').classList.toggle('hidden')" class="btn-accent metal h-11 text-[13px] px-4">+ افزودن حضوری</button>
  <?php endif; ?>
</div>

<div id="walkin-box" class="hidden bg-white rounded-2xl border border-ink-100 p-5 mb-5">
  <h2 class="card-title mb-3">افزودن مراجعهٔ حضوری</h2>
  <form method="post" action="<?= url('panel/queue/walkin') ?>" class="space-y-3">
    <?= csrf_field() ?>
    <div class="grid sm:grid-cols-2 gap-3">
      <!--
        برچسب‌ها sr-only هستند، نه غایب: placeholder به‌محض تایپ کردن
        ناپدید می‌شود و صفحه‌خوان هم آن را نام فیلد حساب نمی‌کند. اینجا
        فرم باید فشرده بماند (آرایشگر وسط کار، مشتری جلوی پیشخوان)،
        پس برچسب هست ولی دیده نمی‌شود.
      -->
      <div>
        <label for="walkin-name" class="sr-only">نام مشتری (اختیاری)</label>
        <input type="text" id="walkin-name" name="name" placeholder="نام مشتری (اختیاری)"
               autocomplete="name"
               class="w-full rounded-xl border border-ink-200 px-3 py-2.5 text-sm">
      </div>
      <div>
        <label for="walkin-phone" class="sr-only">شمارهٔ موبایل (اختیاری)</label>
        <input inputmode="numeric" type="tel" id="walkin-phone" name="phone" dir="ltr"
               autocomplete="tel" placeholder="شمارهٔ موبایل (اختیاری)"
               class="w-full rounded-xl border border-ink-200 px-3 py-2.5 text-sm text-left">
      </div>
    </div>
    <div>
      <label class="block text-xs text-ink-500 mb-1.5" for="staff_id">خدمت(ها)</label>
      <div class="flex flex-wrap gap-2">
        <?php foreach ($services as $s): ?>
        <label class="flex items-center gap-1.5 text-xs bg-ink-50 border border-ink-200 rounded-lg px-2.5 py-1.5 cursor-pointer">
          <input type="checkbox" name="service_ids[]" value="<?= (int)$s['id'] ?>"><?= e($s['name']) ?>
        </label>
        <?php endforeach; ?>
      </div>
    </div>
    <div>
      <label class="block text-xs text-ink-500 mb-1.5">آرایشگر</label>
      <select id="staff_id" name="staff_id" class="rounded-xl border border-ink-200 px-3 py-2.5 text-sm">
        <option value="">فرقی نمی‌کند (کمترین صف)</option>
        <?php foreach ($staffList as $st): ?>
        <option value="<?= (int)$st['id'] ?>"><?= e($st['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button type="submit" class="btn-accent metal px-5">افزودن به صف</button>
  </form>
</div>

<?php if ($role === 'staff' && $myStaffId !== null): ?>
  <?php $mine = null; foreach ($snapshot as $g) { if ((int)$g['staff']['id'] === $myStaffId) { $mine = $g; } } ?>
  <div class="glass rounded-2xl p-5 mb-5 text-center">
    <div class="text-xs text-ink-400 mb-1">امروز تو چقدر درآوردی</div>
    <div class="text-3xl font-extrabold text-accent"><?= toman((int)($todayEarnings['total'] ?? 0)) ?></div>
    <div class="text-xs text-ink-400 mt-1"><?= fa_num($todayCount) ?> نوبت انجام‌شده</div>
  </div>

  <?php if ($mine && !empty($mine['queue'])): $current = $mine['queue'][0]; ?>
    <?php if ($current['status'] === 'in_chair'): ?>
      <div class="glass rounded-2xl p-5 mb-3">
        <div class="text-xs text-ink-400 mb-1">روی صندلی</div>
        <div class="text-xl font-bold text-ink-800"><?= e($current['customer_name'] ?: 'مشتری') ?></div>
        <div class="text-xs text-ink-400 mt-1"><?= e(implode('، ', array_column($current['items'], 'service_name'))) ?></div>
      </div>
      <form method="post" action="<?= url('panel/queue/' . $current['id'] . '/complete') ?>">
        <?= csrf_field() ?>
        <button type="submit" class="btn-done w-full font-extrabold text-xl rounded-2xl py-8">تمام شد</button>
      </form>
    <?php else: ?>
      <div class="glass rounded-2xl p-5 mb-3">
        <div class="text-xs text-ink-400 mb-1">نفر بعدی</div>
        <div class="text-xl font-bold text-ink-800"><?= e($current['customer_name'] ?: 'مشتری') ?></div>
        <div class="text-xs text-ink-400 mt-1"><?= e(implode('، ', array_column($current['items'], 'service_name'))) ?></div>
      </div>
      <form method="post" action="<?= url('panel/queue/' . $current['id'] . '/start') ?>">
        <?= csrf_field() ?>
        <button type="submit" class="btn-accent w-full font-extrabold text-xl rounded-2xl py-8 h-auto">شروع</button>
      </form>
    <?php endif; ?>

    <?php if (count($mine['queue']) > 1): ?>
    <div class="mt-5">
      <h3 class="text-[12px] font-bold text-ink-500 mb-2">در صف</h3>
      <div class="space-y-2">
        <?php foreach (array_slice($mine['queue'], 1) as $row): ?>
        <div class="glass rounded-xl px-4 py-3 flex items-center justify-between">
          <span class="text-sm text-ink-700"><?= e($row['customer_name'] ?: 'مشتری') ?></span>
          <span class="text-xs text-ink-400"><?= e($row['display']['text'] ?? '') ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  <?php else: ?>
    <div class="bg-white rounded-2xl border border-dashed border-ink-200 p-10 text-center text-ink-400">صف شما خالی است.</div>
  <?php endif; ?>

<?php else: ?>
  <div class="grid md:grid-cols-2 xl:grid-cols-3 gap-4">
    <?php foreach ($snapshot as $gi => $group): ?>
    <section class="glass rounded-2xl overflow-hidden shadow-card rise rise-<?= min($gi + 1, 5) ?>"
             aria-label="صف <?= e($group['staff']['name']) ?>">

      <header class="px-4 py-3 flex items-center gap-2.5 border-b" style="border-color:var(--line)">
        <span class="w-8 h-8 rounded-lg grid place-items-center text-[12px] font-extrabold text-white shrink-0"
              style="background:<?= e($group['staff']['color'] ?: '#57534E') ?>" aria-hidden="true">
          <?= e(mb_substr($group['staff']['name'], 0, 1)) ?>
        </span>
        <span class="font-extrabold text-[14px] text-ink-900 flex-1"><?= e($group['staff']['name']) ?></span>
        <?php $n = count($group['queue']); ?>
        <span class="text-[12px] font-bold tabular-nums px-2 py-1 rounded-lg
                     <?= $n > 0 ? 'bg-gold-50 text-accent' : 'text-ink-400' ?>">
          <?= $n > 0 ? e(fa_num($n)) . ' نفر' : 'خالی' ?>
        </span>
      </header>

      <?php if (empty($group['queue'])): ?>
        <div class="px-4 py-8 text-center">
          <p class="text-[12px] text-ink-400">کسی در صف نیست</p>
        </div>
      <?php endif; ?>

      <div class="divide-y" style="--tw-divide-opacity:1">
        <?php foreach ($group['queue'] as $row): $inChair = $row['status'] === 'in_chair'; ?>
        <div class="px-4 py-3.5 <?= $inChair ? 'bg-gold-50/50' : '' ?>"
             style="border-color:var(--line)">

          <div class="flex items-start justify-between gap-3 mb-2.5">
            <div class="min-w-0">
              <div class="flex items-center gap-1.5 flex-wrap">
                <?php if ($inChair): ?>
                  <span class="relative flex w-2 h-2 shrink-0" aria-hidden="true">
                    <span class="absolute inline-flex w-full h-full rounded-full bg-gold-500 opacity-70 animate-ping"></span>
                    <span class="relative inline-flex w-2 h-2 rounded-full bg-gold-600"></span>
                  </span>
                <?php endif; ?>
                <span class="text-[14px] font-extrabold text-ink-900 truncate">
                  <?= e($row['customer_name'] ?: 'مشتری') ?>
                </span>
                <?php if ($row['kind'] === 'booked'): ?>
                  <span class="text-[12px] font-bold bg-ink-100 text-ink-600 rounded px-1.5 py-0.5 whitespace-nowrap">رزرو</span>
                <?php endif; ?>
              </div>
              <p class="text-[12px] text-ink-500 mt-0.5 truncate">
                <?= e(implode('، ', array_column($row['items'], 'service_name'))) ?>
              </p>
            </div>

            <span class="text-[12px] font-bold shrink-0 text-left tabular-nums
                         <?= $inChair ? 'text-accent' : 'text-ink-500' ?>">
              <?= e($row['display']['text'] ?? '') ?>
            </span>
          </div>

          <!--
            دکمه‌ها ۴۴ پیکسل‌اند، نه ۲۲. آرایشگری که قیچی دستش است،
            دکمهٔ ریز را نمی‌زند — یا بدتر، اشتباهی «لغو» را می‌زند.
            کنشِ اصلی رنگِ پالت را دارد و کنش‌های خطرناک فقط متن‌اند.
            پیش‌تر «شروع» خاکستری بود — همان خاکستریِ دکمهٔ غیرفعال — و
            در عکس صفحه شبیه دکمه‌ای می‌شد که کار نمی‌کند. مسیر رنگ‌ها
            حالا خوانا است: طلایی «شروع کن»، سبز «تمام شد».
          -->
          <div class="flex items-center gap-2">
            <?php if (!$inChair): ?>
              <form method="post" action="<?= e(url('panel/queue/' . $row['id'] . '/start')) ?>" class="flex-1">
                <?= csrf_field() ?>
                <button class="btn-accent w-full h-11 text-[13px]">شروع</button>
              </form>
            <?php else: ?>
              <form method="post" action="<?= e(url('panel/queue/' . $row['id'] . '/complete')) ?>" class="flex-1">
                <?= csrf_field() ?>
                <!--
                  سبز، نه رنگِ پالت: «تمام شد» تنها کنشی است که آرایشگر
                  وسط کار و بدون خواندن باید پیدایش کند، پس هرجا بیاید
                  یک رنگ دارد. پیش از این نسخهٔ بزرگش سبز بود و همین
                  نسخهٔ فهرستی آبی — یک کار با دو رنگ.
                -->
                <button class="btn-done w-full h-11 text-[13px]">تمام شد و تسویه</button>
              </form>
            <?php endif; ?>

            <form method="post" action="<?= e(url('panel/queue/' . $row['id'] . '/no-show')) ?>">
              <?= csrf_field() ?>
              <button class="h-11 min-w-11 px-3.5 rounded-xl text-[12px] font-semibold text-ink-500
                             hover:bg-ink-100 transition-colors cursor-pointer
                             focus-visible:outline-2 focus-visible:outline-ink-400"
                      aria-label="ثبت غیبت برای <?= e($row['customer_name'] ?: 'مشتری') ?>">غیبت</button>
            </form>

            <form method="post" action="<?= e(url('panel/queue/' . $row['id'] . '/cancel')) ?>"
                  onsubmit="return confirm('این نوبت لغو شود؟')">
              <?= csrf_field() ?>
              <button class="h-11 min-w-11 px-3.5 rounded-xl text-[12px] font-semibold text-red-600
                             hover:bg-red-50 transition-colors cursor-pointer
                             focus-visible:outline-2 focus-visible:outline-red-500"
                      aria-label="لغو نوبت <?= e($row['customer_name'] ?: 'مشتری') ?>">لغو</button>
            </form>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
