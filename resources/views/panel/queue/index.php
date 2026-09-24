<?php
/** @var array $snapshot
 * @var ?int $myStaffId
 * @var array $services
 * @var array $staffList
 * @var int $todayCount
 * @var array $todayEarnings
 * @var array $todaySummary
 * @var ?array $salonEarnings
 * @var array $awaitingPayment
 * @var string $pollEtag
 */
use App\Core\Auth;
use App\Support\JalaliCalendar;

$role = Auth::role();
$today = new DateTimeImmutable('today');
$sum = $todaySummary ?? ['total'=>0,'completed'=>0,'waiting'=>0,'in_chair'=>0,'no_show'=>0];
$awaitingPayment ??= [];

/*
 * زمانِ هر ردیف، به زبانِ آرایشگر.
 *
 * EtaEngine جمله را برای مشتری می‌سازد: «نوبت بعدی توست». روی پنل،
 * خواننده آرایشگر است و همان جمله به *او* می‌گفت «نوبتِ توست». نفرِ
 * اولِ صف اینجا «نفر بعدی» است.
 */
$etaText = static fn (array $row): string => !empty($row['display']['next'])
    ? 'نفر بعدی'
    : (string) ($row['display']['text'] ?? '');
?>

<!--
  نوار خلاصهٔ امروز.

  چرا تاریخ شمسی اینجاست: صاحب سالن روز را با تاریخ شمسی می‌شناسد، و
  «پنج‌شنبه» برایش معنای عملیاتی دارد (شلوغ‌ترین روز هفته). تاریخ میلادی
  یا نبودِ تاریخ، این صفحه را از واقعیت جدا می‌کند.
-->
<div class="glass rounded-2xl p-4 mb-4 rise hairline-accent">
  <div class="flex items-baseline justify-between gap-3 mb-3">
    <div class="min-w-0">
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
        <div class="text-xl font-extrabold text-accent tabular-nums whitespace-nowrap">
          <?= e(toman((int) ($salonEarnings['total'] ?? 0))) ?>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <?php
  /*
   * هر خانه رنگِ معنایی‌اش را از توکن می‌گیرد، نه از پلهٔ ثابتِ
   * تیلویند. پیش‌تر ‎bg-green-50‎ و ‎bg-gold-50‎ بودند و حالت تیره فقط
   * با وصله‌های جداگانه درست می‌شد.
   */
  ?>
  <dl class="grid grid-cols-4 gap-2">
    <?php foreach ([
      ['در انتظار', $sum['waiting'],   'text-accent',  'var(--accent-soft)'],
      ['روی صندلی', $sum['in_chair'],  'text-ink-900', 'var(--fill-secondary)'],
      ['انجام‌شده', $sum['completed'], 'text-ok',      'var(--ok-soft)'],
      ['غیبت',      $sum['no_show'],   'text-ink-500', 'var(--fill-tertiary)'],
    ] as [$label, $value, $fg, $bg]): ?>
      <div class="rounded-xl py-2 px-1 text-center" style="background:<?= $bg ?>">
        <dd class="text-xl font-extrabold <?= $fg ?> tabular-nums"><?= e(fa_num((int) $value)) ?></dd>
        <dt class="text-[12px] text-ink-500 mt-0.5"><?= e($label) ?></dt>
      </div>
    <?php endforeach; ?>
  </dl>
</div>

<?php if ($awaitingPayment !== []): ?>
  <!--
    منتظر تسویه.

    کارهای امروز که تمام شده‌اند و پولشان ثبت نشده — بیشترشان از
    آرایشگری که دسترسیِ تسویه ندارد و «تمام شد» را زده. پیش‌تر هیچ‌جا
    فهرست نمی‌شدند: داشبورد می‌گفت «۲ نوبت تسویه‌نشده» و به همین صفحه
    لینک می‌داد، و اینجا چیزی برای تسویه نبود. پولی که ثبت نشود، در
    گزارش فروش هم نیست.
  -->
  <section id="awaiting-payment" class="glass rounded-2xl overflow-hidden mb-4 rise"
           aria-labelledby="awaiting-title">
    <header class="flex items-center gap-2.5 px-4 py-3">
      <span class="w-8 h-8 rounded-lg grid place-items-center text-bad shrink-0"
            style="background:var(--bad-soft)" aria-hidden="true"><?= icon('wallet', 'w-4 h-4') ?></span>
      <h2 id="awaiting-title" class="card-title flex-1">منتظر تسویه</h2>
      <span class="text-[12px] font-bold text-bad tabular-nums"><?= e(fa_num(count($awaitingPayment))) ?> نفر</span>
    </header>
    <?php foreach ($awaitingPayment as $u): ?>
      <div class="flex items-center gap-3 px-4 py-2.5 border-t" style="border-color:var(--line)">
        <div class="min-w-0 flex-1">
          <div class="text-[14px] font-bold text-ink-900 truncate"><?= e($u['customer_name'] ?: 'مشتری') ?></div>
          <div class="text-[12px] text-ink-500 truncate tabular-nums">
            <span class="font-bold text-ink-700"><?= e(toman($u['total'])) ?></span>
            <?php if (!empty($u['staff_name'])): ?> · <?= e($u['staff_name']) ?><?php endif; ?>
            · <?= e(fa_time(substr($u['actual_end_at'], 11, 5))) ?>
          </div>
        </div>
        <a href="<?= e(url('panel/pay/' . $u['id'])) ?>"
           class="btn-accent h-11 px-4 text-[13px] shrink-0">تسویه</a>
      </div>
    <?php endforeach; ?>
  </section>
<?php endif; ?>

<div class="flex items-center justify-between gap-3 mb-4">
  <h1 class="page-title">صف زنده</h1>
  <?php if (in_array($role, ['owner','manager','reception'], true)): ?>
  <button type="button" id="walkin-toggle" aria-expanded="false" aria-controls="walkin-box"
          class="btn-accent h-11 text-[13px] px-4">
    <?= icon('plus', 'w-4 h-4') ?>
    مراجعهٔ حضوری
  </button>
  <?php endif; ?>
</div>

<?php if (in_array($role, ['owner','manager','reception'], true)): ?>
<!--
  مراجعهٔ حضوری — پرتکرارترین کارِ پیشخوان.

  ترتیب فیلدها ترتیبِ گفت‌وگوی جلوی پیشخوان است: «چی کار داری؟» (خدمت،
  تنها فیلدِ لازم)، «شماره‌ت؟»، و بقیه اختیاری. کمترین مسیر: باز کردن،
  یک خدمت، «افزودن» — سه ضربه.

  خدمت‌ها کارتِ ۴۴ پیکسلی‌اند، نه چک‌باکسِ خامِ مرورگر: پیش‌تر هدفِ
  لمس‌شان ۲۸ پیکسل بود و روی گوشی، کنارِ هم، اشتباهی زده می‌شدند.
-->
<div id="walkin-box" class="hidden glass rounded-2xl p-4 mb-5">
  <form method="post" action="<?= e(url('panel/queue/walkin')) ?>" id="walkin-form" class="space-y-4">
    <?= csrf_field() ?>

    <fieldset>
      <legend class="block text-[12px] font-bold text-ink-600 mb-2">خدمت</legend>
      <div class="grid grid-cols-2 gap-1.5" id="walkin-services">
        <?php foreach ($services as $s): ?>
          <label class="pick block relative tap">
            <input type="checkbox" name="service_ids[]" value="<?= (int) $s['id'] ?>" class="sr-only">
            <span class="pick-card glass flex items-center gap-2 rounded-xl px-3 py-2 min-h-11
                         transition-all duration-200 ease-out-soft cursor-pointer">
              <span class="pick-box w-5 h-5 shrink-0 rounded-md border-2 border-ink-300 grid place-items-center"
                    aria-hidden="true">
                <?= icon('check', 'pick-tick w-3 h-3 opacity-0 transition-opacity duration-200') ?>
              </span>
              <span class="flex-1 min-w-0 text-[13px] font-bold text-ink-800 leading-snug"><?= e($s['name']) ?></span>
            </span>
          </label>
        <?php endforeach; ?>
      </div>
      <p id="walkin-missing" class="hidden text-[12px] font-bold text-bad mt-2" role="alert">
        اول خدمت را انتخاب کن.
      </p>
    </fieldset>

    <div>
      <label for="walkin-phone" class="block text-[12px] font-bold text-ink-600 mb-1.5">
        موبایل <span class="font-normal text-ink-400">(اختیاری)</span>
      </label>
      <input inputmode="numeric" type="tel" id="walkin-phone" name="phone" dir="ltr"
             autocomplete="off" placeholder="۰۹۱۲۳۴۵۶۷۸۹"
             class="field text-left tabular-nums">
      <p class="text-[12px] text-ink-400 mt-1.5 leading-relaxed">
        با شماره، نزدیکِ نوبتش پیامک می‌گیرد — لازم نیست همین‌جا منتظر بماند.
      </p>
    </div>

    <div class="grid sm:grid-cols-2 gap-3">
      <div>
        <label for="walkin-name" class="block text-[12px] font-bold text-ink-600 mb-1.5">
          نام <span class="font-normal text-ink-400">(اختیاری)</span>
        </label>
        <input type="text" id="walkin-name" name="name" autocomplete="off" class="field">
      </div>
      <div>
        <label for="walkin-staff" class="block text-[12px] font-bold text-ink-600 mb-1.5">آرایشگر</label>
        <select id="walkin-staff" name="staff_id" class="field">
          <option value="">هرکس صفش کوتاه‌تر است</option>
          <?php foreach ($staffList as $st): ?>
          <option value="<?= (int) $st['id'] ?>"><?= e($st['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <button type="submit" class="btn-accent w-full">افزودن به صف</button>
  </form>
</div>
<?php endif; ?>

<?php if ($role === 'staff' && $myStaffId !== null): ?>
  <?php $mine = null; foreach ($snapshot as $g) { if ((int)$g['staff']['id'] === $myStaffId) { $mine = $g; } } ?>

  <?php if ($mine && !empty($mine['queue'])): $current = $mine['queue'][0]; ?>
    <?php
    /*
     * دکمهٔ بزرگ — ۸۰ پیکسل، تمامِ عرض.
     *
     * آرایشگر وسط کار، با یک دست و بی‌آنکه بخواند، این را می‌زند. پیش‌تر
     * قدش از ‎py-8‎ روی دکمه‌ای با ارتفاعِ ثابت درمی‌آمد و «شروع» اصلاً
     * رنگ نداشت (کلاسِ رنگ‌دهنده جا مانده بود) — متنی شناور وسط صفحه.
     */
    ?>
    <?php if ($current['status'] === 'in_chair'): ?>
      <div class="glass rounded-2xl p-5 mb-3">
        <div class="text-[12px] text-ink-400 mb-1">روی صندلی</div>
        <div class="text-xl font-bold text-ink-800"><?= e($current['customer_name'] ?: 'مشتری') ?></div>
        <div class="text-[12px] text-ink-400 mt-1"><?= e(implode('، ', array_column($current['items'], 'service_name'))) ?></div>
      </div>
      <form method="post" action="<?= e(url('panel/queue/' . $current['id'] . '/complete')) ?>">
        <?= csrf_field() ?>
        <button type="submit" class="btn-done w-full h-20 text-xl font-extrabold rounded-2xl">
          <?= icon('check', 'w-6 h-6') ?>
          تمام شد
        </button>
      </form>
    <?php else: ?>
      <div class="glass rounded-2xl p-5 mb-3">
        <div class="text-[12px] text-ink-400 mb-1">نفر بعدی</div>
        <div class="text-xl font-bold text-ink-800"><?= e($current['customer_name'] ?: 'مشتری') ?></div>
        <div class="text-[12px] text-ink-400 mt-1"><?= e(implode('، ', array_column($current['items'], 'service_name'))) ?></div>
      </div>
      <form method="post" action="<?= e(url('panel/queue/' . $current['id'] . '/start')) ?>">
        <?= csrf_field() ?>
        <button type="submit" class="btn-accent w-full h-20 text-xl font-extrabold rounded-2xl">
          <?= icon('play', 'w-6 h-6') ?>
          شروع
        </button>
      </form>
    <?php endif; ?>

    <?php if (count($mine['queue']) > 1): ?>
    <div class="mt-5">
      <h3 class="text-[12px] font-bold text-ink-500 mb-2">در صف</h3>
      <div class="glass rounded-2xl overflow-hidden">
        <?php foreach (array_slice($mine['queue'], 1) as $i => $row): ?>
        <div class="px-4 py-3 flex items-center justify-between gap-3 <?= $i > 0 ? 'border-t' : '' ?>"
             style="border-color:var(--line)">
          <span class="text-[14px] text-ink-800 truncate"><?= e($row['customer_name'] ?: 'مشتری') ?></span>
          <span class="text-[12px] text-ink-500 tabular-nums shrink-0"><?= e($etaText($row)) ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  <?php else: ?>
    <div class="glass rounded-2xl px-5 py-10 text-center">
      <span class="w-11 h-11 mx-auto mb-2 rounded-full grid place-items-center text-ink-400"
            style="background:var(--fill-secondary)" aria-hidden="true"><?= icon('armchair', 'w-5 h-5') ?></span>
      <p class="text-[13px] text-ink-500">صف شما خالی است.</p>
    </div>
  <?php endif; ?>

  <!--
    درآمدِ امروز — پایینِ صفحه، نه بالا.

    پیش‌تر اولین کارتِ صفحه بود و مشتریِ روی صندلی و دکمهٔ «تمام شد» را
    تا نیمهٔ صفحه پایین می‌برد. وسط کار، آرایشگر دنبالِ دکمه است؛ عدد
    را آخر روز نگاه می‌کند.
  -->
  <div class="glass rounded-2xl px-4 py-3.5 mt-5 flex items-center justify-between gap-3">
    <div>
      <div class="text-[12px] text-ink-400">درآمدِ امروزِ تو</div>
      <div class="text-[12px] text-ink-500 mt-0.5"><?= e(fa_num($todayCount)) ?> نوبت انجام‌شده</div>
    </div>
    <div class="text-xl font-extrabold text-accent tabular-nums"><?= e(toman((int)($todayEarnings['total'] ?? 0))) ?></div>
  </div>

<?php else: ?>
  <div class="grid md:grid-cols-2 xl:grid-cols-3 gap-4">
    <?php foreach ($snapshot as $gi => $group): ?>
    <section class="glass rounded-2xl overflow-hidden shadow-card rise rise-<?= min($gi + 1, 5) ?>"
             aria-label="صف <?= e($group['staff']['name']) ?>">

      <header class="px-4 py-3 flex items-center gap-2.5 border-b" style="border-color:var(--line)">
        <span class="w-8 h-8 rounded-lg grid place-items-center text-[12px] font-extrabold text-white on-tint shrink-0"
              style="background:<?= e($group['staff']['color'] ?: '#4A565D') ?>" aria-hidden="true">
          <?= e(mb_substr($group['staff']['name'], 0, 1)) ?>
        </span>
        <span class="font-extrabold text-[14px] text-ink-900 flex-1"><?= e($group['staff']['name']) ?></span>
        <?php $n = count($group['queue']); ?>
        <span class="text-[12px] font-bold tabular-nums px-2 py-1 rounded-lg <?= $n > 0 ? 'chip-accent' : 'text-ink-400' ?>">
          <?= $n > 0 ? e(fa_num($n)) . ' نفر' : 'خالی' ?>
        </span>
      </header>

      <?php if (empty($group['queue'])): ?>
        <div class="px-4 py-8 text-center">
          <p class="text-[12px] text-ink-400">کسی در صف نیست</p>
        </div>
      <?php endif; ?>

      <?php
      /*
       * «شروع» فقط وقتی معنا دارد که صندلی خالی است.
       *
       * پیش‌تر هر ردیفِ منتظر دکمهٔ «شروع» داشت، حتی وقتی کسی روی
       * صندلیِ همان آرایشگر بود — و زدنش فقط خطای «این آرایشگر همین الان
       * مشغول است» برمی‌گرداند. حالا تا صندلی پر است، ردیف‌های منتظر
       * «شروع» ندارند. وقتی خالی شد، نفرِ بعدی دکمهٔ پررنگ می‌گیرد و
       * بقیه نسخهٔ کم‌رنگ — آرایشگر هنوز می‌تواند کسِ دیگری را زودتر
       * بنشاند (مشتریِ رزروی که رسیده)، ولی چشمش اول به نفرِ درست می‌رود.
       */
      $chairBusy = in_array('in_chair', array_column($group['queue'], 'status'), true);
      $nextId = null;
      foreach ($group['queue'] as $r) {
          if ($r['status'] !== 'in_chair') { $nextId = (int) $r['id']; break; }
      }
      ?>
      <div>
        <?php foreach ($group['queue'] as $ri => $row): $inChair = $row['status'] === 'in_chair'; ?>
        <div class="px-4 py-3.5 <?= $ri > 0 ? 'border-t' : '' ?>"
             style="border-color:var(--line)<?= $inChair ? ';background:var(--accent-soft)' : '' ?>">

          <div class="flex items-start justify-between gap-3 mb-2.5">
            <div class="min-w-0">
              <div class="flex items-center gap-1.5 flex-wrap">
                <?php if ($inChair): ?>
                  <span class="relative flex w-2 h-2 shrink-0" aria-hidden="true">
                    <span class="absolute inline-flex w-full h-full rounded-full bg-accent opacity-60 animate-ping"></span>
                    <span class="relative inline-flex w-2 h-2 rounded-full bg-accent"></span>
                  </span>
                <?php endif; ?>
                <span class="text-[14px] font-extrabold text-ink-900 truncate">
                  <?= e($row['customer_name'] ?: 'مشتری') ?>
                </span>
                <?php if ($row['kind'] === 'booked'): ?>
                  <span class="text-[12px] font-bold text-ink-600 rounded px-1.5 py-0.5 whitespace-nowrap"
                        style="background:var(--fill-secondary)">رزرو</span>
                  <?php
                  /*
                   * «دیر کرده» — مشتریِ رزروی که از پنجرهٔ اولویتش گذشته و
                   * هنوز نیامده.
                   *
                   * بدون این، آرایشگر نمی‌داند منتظر بماند یا نفر بعدی را
                   * بنشاند. با این، یا زنگ می‌زند یا «غیبت» می‌زند — و هر
                   * دو، تخمینِ بقیهٔ صف را به واقعیت برمی‌گردانند. مرزش
                   * همان پنجره‌ای است که QueueOrderingService اولویت را با
                   * آن می‌سنجد، تا چیپ و ترتیبِ صف یک حرف بزنند.
                   */
                  $late = null;
                  if (!$inChair && ($row['status'] ?? '') === 'confirmed' && !empty($row['scheduled_at'])) {
                      $window = (int) App\Core\Config::get('reshen.queue.priority_window_minutes', 10);
                      $overdue = (int) floor((time() - strtotime((string) $row['scheduled_at'])) / 60);
                      if ($overdue > $window) {
                          $late = $overdue;
                      }
                  }
                  ?>
                  <?php if ($late !== null): ?>
                    <span class="text-[12px] font-bold rounded px-1.5 py-0.5 whitespace-nowrap text-bad"
                          style="background:var(--bad-soft)">
                      <?= $late < 60
                          ? e(fa_num($late)) . ' دقیقه دیر'
                          : e(fa_num(intdiv($late, 60))) . ' ساعت دیر' ?>
                    </span>
                  <?php endif; ?>
                <?php endif; ?>
              </div>
              <p class="text-[12px] text-ink-500 mt-0.5 truncate">
                <?= e(implode('، ', array_column($row['items'], 'service_name'))) ?>
              </p>
            </div>

            <span class="text-[12px] font-bold shrink-0 text-left tabular-nums
                         <?= $inChair || !empty($row['display']['next']) ? 'text-accent' : 'text-ink-500' ?>">
              <?= $inChair ? 'روی صندلی' : e($etaText($row)) ?>
            </span>
          </div>

          <!--
            دکمه‌ها ۴۴ پیکسل‌اند، نه ۲۲. آرایشگری که قیچی دستش است،
            دکمهٔ ریز را نمی‌زند — یا بدتر، اشتباهی «لغو» را می‌زند.
            کنشِ اصلی رنگِ پالت را دارد و کنش‌های خطرناک فقط متن‌اند.
            مسیر رنگ‌ها خوانا است: رنگِ سالن «شروع کن»، سبز «تمام شد».
          -->
          <div class="flex items-center gap-2">
            <?php if (!$inChair && $chairBusy): ?>
              <span class="flex-1 text-[12px] text-ink-400">بعد از نفرِ روی صندلی</span>
            <?php elseif (!$inChair): ?>
              <form method="post" action="<?= e(url('panel/queue/' . $row['id'] . '/start')) ?>" class="flex-1">
                <?= csrf_field() ?>
                <button class="<?= (int) $row['id'] === $nextId ? 'btn-accent' : 'btn-tint' ?> w-full h-11 text-[13px]">
                  <?= icon('play', 'w-4 h-4') ?>
                  شروع
                </button>
              </form>
            <?php else: ?>
              <form method="post" action="<?= e(url('panel/queue/' . $row['id'] . '/complete')) ?>" class="flex-1">
                <?= csrf_field() ?>
                <!--
                  سبز، نه رنگِ پالت: «تمام شد» تنها کنشی است که آرایشگر
                  وسط کار و بدون خواندن باید پیدایش کند، پس هرجا بیاید
                  یک رنگ دارد.
                -->
                <!-- زیر ۳۶۰ پیکسل «و تسویه» جا نمی‌شود و دکمه دوخطی می‌شد. -->
                <button class="btn-done w-full h-11 px-3 text-[13px] whitespace-nowrap">
                  <?= icon('check', 'w-4 h-4') ?>
                  <span>تمام شد<span class="hidden min-[360px]:inline"> و تسویه</span></span>
                </button>
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
              <button class="h-11 min-w-11 px-3.5 rounded-xl text-[12px] font-semibold text-bad
                             hover:bg-bad-soft transition-colors cursor-pointer
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

<script>
/*
 * فرمِ حضوری: باز و بسته، و خدمتِ جاافتاده.
 *
 * خدمت تنها فیلدِ لازم است ولی گروهِ چک‌باکس را مرورگر نمی‌تواند
 * «لازم» کند. پیش‌تر فرمِ بی‌خدمت به سرور می‌رفت، خطا برمی‌گشت و
 * نام و شماره‌ای که پذیرش نوشته بود پاک می‌شد.
 */
(function () {
  var toggle = document.getElementById('walkin-toggle');
  var box = document.getElementById('walkin-box');
  var form = document.getElementById('walkin-form');
  if (!toggle || !box || !form) return;

  toggle.addEventListener('click', function () {
    var open = box.classList.toggle('hidden') === false;
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
  });

  var group = document.getElementById('walkin-services');
  var missing = document.getElementById('walkin-missing');
  form.addEventListener('submit', function (e) {
    if (form.querySelector('input[name="service_ids[]"]:checked')) return;
    e.preventDefault();
    missing.classList.remove('hidden');
    group.classList.remove('needs-pick');
    void group.offsetWidth;            // تا پالس دوباره پخش شود
    group.classList.add('needs-pick');
    group.scrollIntoView({ block: 'center', behavior: 'smooth' });
  });
  group.addEventListener('change', function () {
    missing.classList.add('hidden');
    group.classList.remove('needs-pick');
  });
})();

/*
 * تازه‌سازیِ صف، بدون پاک کردنِ کارِ نیمه‌تمام.
 *
 * پیش‌تر صفحه هر ۱۵ ثانیه بی‌قیدوشرط دوباره بارگذاری می‌شد. پذیرشی که
 * وسطِ نوشتنِ شمارهٔ مشتریِ حضوری بود، فرم را جلوی چشمش از دست می‌داد؛
 * و روی اینترنتِ همراه، صفحه‌ای که هیچ‌چیزش عوض نشده بود دقیقه‌ای چهار
 * بار کامل دانلود می‌شد.
 *
 * حالا هر ۱۵ ثانیه فقط اثرِ انگشتِ صف (ETag) پرسیده می‌شود و پاسخِ
 * «عوض نشده» چند بایت است. اگر صف عوض شده باشد، صفحه بارگذاری می‌شود
 * — ولی نه وقتی فرمی باز است، کسی در فیلدی می‌نویسد، یا برگه پنهان
 * است؛ آن‌وقت می‌ماند برای اولین لحظه‌ای که مانعی نیست.
 *
 * پرسیدن هیچ‌وقت قطع نمی‌شود، حتی در برگهٔ پنهان: زمان‌بندِ برنامه با
 * همین درخواست‌ها بیدار می‌شود (ت-۳۸)، و یادآورهای پیامکی به آن
 * بسته‌اند. مرورگر خودش برگهٔ پنهان را کُند می‌کند؛ ما قطعش نمی‌کنیم.
 */
(function () {
  var etag = <?= json_encode($pollEtag ?? '') ?>;
  var url = <?= json_encode(url('panel/queue/poll')) ?>;
  var stale = false;

  function busy() {
    var box = document.getElementById('walkin-box');
    if (box && !box.classList.contains('hidden')) return true;
    if (document.querySelector('details[open]')) return true;
    var a = document.activeElement;
    return !!a && /^(INPUT|SELECT|TEXTAREA)$/.test(a.tagName);
  }

  function reloadIfStale() {
    if (stale && !document.hidden && !busy()) location.reload();
  }

  function check() {
    if (!window.fetch) return;
    fetch(url, { headers: { 'If-None-Match': etag }, credentials: 'same-origin', cache: 'no-store' })
      .then(function (r) { if (r.status === 200) { stale = true; reloadIfStale(); } })
      .catch(function () {});
  }

  setInterval(function () { reloadIfStale(); check(); }, 15000);
  document.addEventListener('visibilitychange', reloadIfStale);
})();
</script>
