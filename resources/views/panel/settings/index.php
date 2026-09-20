<?php
/** @var array $salon
 * @var array $hours
 * @var array $weekdayNames
 * @var array $holidays
 * @var int $currentJalaliYear
 */
?>
<h1 class="text-lg font-bold text-ink-800 mb-5">تنظیمات سالن</h1>

<div class="grid lg:grid-cols-2 gap-5">
  <div class="glass rounded-2xl p-5">
    <h2 class="text-sm font-bold text-ink-700 mb-4">مشخصات سالن</h2>
    <form method="post" action="<?= url('panel/settings/profile') ?>" class="space-y-3">
      <?= csrf_field() ?>
      <div>
        <label class="block text-xs text-ink-500 mb-1">نام سالن</label>
        <input type="text" name="name" value="<?= e($salon['name']) ?>" class="w-full rounded-xl border border-ink-200 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-accent">
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-xs text-ink-500 mb-1">شهر</label>
          <input type="text" name="city" value="<?= e($salon['city'] ?? '') ?>" class="w-full rounded-xl border border-ink-200 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-accent">
        </div>
        <div>
          <label class="block text-xs text-ink-500 mb-1">تلفن</label>
          <input type="text" dir="ltr" name="phone" value="<?= e($salon['phone'] ?? '') ?>" class="w-full rounded-xl border border-ink-200 px-3 py-2.5 text-sm text-left focus:outline-none focus:ring-2 focus:ring-accent">
        </div>
      </div>
      <div>
        <label class="block text-xs text-ink-500 mb-1">آدرس</label>
        <input type="text" name="address" value="<?= e($salon['address'] ?? '') ?>" class="w-full rounded-xl border border-ink-200 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-accent">
      </div>

      <div>
        <label class="block text-xs font-bold text-ink-600 mb-2">رنگ صفحهٔ سالن</label>
        <p class="text-[11.5px] text-ink-400 mb-3">
          صفحه‌ای که مشتری می‌بیند با این رنگ نمایش داده می‌شود.
        </p>

        <fieldset class="grid grid-cols-3 gap-2">
          <legend class="sr-only">انتخاب پالت رنگی</legend>
          <?php $current = App\Support\Theme::resolve($salon['theme'] ?? null); ?>
          <?php foreach (App\Support\Theme::all() as $key => $palette): ?>
            <label class="pick relative block tap">
              <input type="radio" name="theme" value="<?= e($key) ?>" class="sr-only"
                     <?= $key === $current ? 'checked' : '' ?>>
              <span class="pick-card glass flex items-center gap-2 rounded-xl px-3 py-2.5
                           transition-all duration-200 ease-out-soft cursor-pointer">
                <span class="w-5 h-5 rounded-full shrink-0 ring-1 ring-black/10"
                      style="background:<?= e($palette['swatch']) ?>" aria-hidden="true"></span>
                <span class="text-[12.5px] font-semibold text-ink-800"><?= e($palette['name']) ?></span>
              </span>
            </label>
          <?php endforeach; ?>
        </fieldset>
      </div>
      <div class="bg-ink-50 rounded-xl px-3 py-2.5 text-xs text-ink-500">
        لینک عمومی رزرو: <span dir="ltr" class="font-mono text-accent"><?= e(url('s/' . $salon['slug'])) ?></span>
      </div>
      <div class="flex items-center justify-between bg-ink-50 rounded-xl px-3 py-2.5 text-xs">
        <span class="text-ink-500">موجودی کیف پیامک</span>
        <span class="font-bold <?= (int)$salon['sms_credit'] <= 30 ? 'text-amber-600' : 'text-ink-700' ?>"><?= fa_num($salon['sms_credit']) ?> پیامک</span>
      </div>
      <button type="submit" class="btn-accent metal w-full">ذخیره</button>
    </form>
  </div>

  <div class="glass rounded-2xl p-5">
    <div class="flex items-baseline justify-between mb-1">
      <h2 class="text-sm font-extrabold text-ink-900">ساعت کاری و سانس‌بندی</h2>
    </div>
    <p class="text-[11.5px] text-ink-400 mb-4">
      سانس‌های قابل رزرو از همین ساعت‌ها ساخته می‌شوند. استراحت، آن بازه را از رزرو درمی‌آورد.
    </p>

    <form method="post" action="<?= e(url('panel/settings/hours')) ?>" class="space-y-3">
      <?= csrf_field() ?>

      <div>
        <label for="slot-step" class="block text-xs font-bold text-ink-600 mb-1.5">طول هر سانس</label>
        <select name="slot_step_minutes" id="slot-step"
                class="w-full h-11 rounded-xl border border-ink-200 bg-transparent px-3 text-sm
                       focus:outline-none focus:ring-2 focus:ring-accent">
          <?php $step = (int) ($salon['slot_step_minutes'] ?? 15); ?>
          <?php foreach ([10, 15, 20, 30, 45, 60] as $m): ?>
            <option value="<?= $m ?>" <?= $m === $step ? 'selected' : '' ?>>
              هر <?= e(fa_num($m)) ?> دقیقه
            </option>
          <?php endforeach; ?>
        </select>
        <p class="text-[11px] text-ink-400 mt-1.5">
          فاصلهٔ بین زمان‌هایی که مشتری می‌بیند. کوتاه‌تر یعنی گزینهٔ بیشتر، ولی فهرست شلوغ‌تر.
        </p>
      </div>

      <div class="h-px my-1" style="background:var(--line)" aria-hidden="true"></div>

      <?php foreach ($weekdayNames as $i => $name):
          $h = $hours[$i] ?? null;
          $closed = $h ? (bool) $h['is_closed'] : false;
          $bs = $h['break_start'] ?? null;
          $be = $h['break_end'] ?? null;
      ?>
        <fieldset class="rounded-xl p-2.5 <?= $closed ? 'opacity-55' : '' ?>"
                  style="background:var(--accent-soft)">
          <legend class="sr-only"><?= e($name) ?></legend>

          <div class="flex items-center gap-2 mb-2">
            <span class="w-14 shrink-0 text-[13px] font-bold text-ink-800"><?= e($name) ?></span>

            <input type="time" name="opens_<?= $i ?>"
                   value="<?= e(substr((string) ($h['opens_at'] ?? '09:00:00'), 0, 5)) ?>"
                   aria-label="ساعت باز شدن <?= e($name) ?>"
                   class="h-10 rounded-lg border border-ink-200 bg-transparent px-2 text-xs tabular-nums">
            <span class="text-ink-400 text-xs">تا</span>
            <input type="time" name="closes_<?= $i ?>"
                   value="<?= e(substr((string) ($h['closes_at'] ?? '21:00:00'), 0, 5)) ?>"
                   aria-label="ساعت بسته شدن <?= e($name) ?>"
                   class="h-10 rounded-lg border border-ink-200 bg-transparent px-2 text-xs tabular-nums">

            <label class="flex items-center gap-1.5 text-[11.5px] text-ink-600 mr-auto cursor-pointer">
              <input type="checkbox" name="closed_<?= $i ?>" <?= $closed ? 'checked' : '' ?>
                     class="w-4 h-4 accent-current">
              تعطیل
            </label>
          </div>

          <div class="flex items-center gap-2 ps-16">
            <span class="text-[11px] text-ink-500 shrink-0">استراحت</span>
            <input type="time" name="break_start_<?= $i ?>" value="<?= e($bs ? substr($bs, 0, 5) : '') ?>"
                   aria-label="شروع استراحت <?= e($name) ?>"
                   class="h-9 rounded-lg border border-ink-200 bg-transparent px-2 text-[11px] tabular-nums">
            <span class="text-ink-400 text-[11px]">تا</span>
            <input type="time" name="break_end_<?= $i ?>" value="<?= e($be ? substr($be, 0, 5) : '') ?>"
                   aria-label="پایان استراحت <?= e($name) ?>"
                   class="h-9 rounded-lg border border-ink-200 bg-transparent px-2 text-[11px] tabular-nums">
            <span class="text-[10.5px] text-ink-400 mr-auto">اختیاری</span>
          </div>
        </fieldset>
      <?php endforeach; ?>

      <button type="submit" class="btn-accent metal w-full mt-2">ذخیره ساعت کاری</button>
    </form>
  </div>

  <div class="glass rounded-2xl p-5 lg:col-span-2">
    <div class="flex items-center justify-between mb-4">
      <h2 class="text-sm font-bold text-ink-700">تعطیلات</h2>
      <form method="post" action="<?= url('panel/settings/holidays/seed') ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="jalali_year" value="<?= (int)$currentJalaliYear ?>">
        <button class="text-xs bg-ink-100 hover:bg-ink-200 rounded-lg px-3 py-1.5">افزودن تعطیلات ثابت <?= fa_num($currentJalaliYear) ?></button>
      </form>
    </div>
    <div class="space-y-1.5 mb-4">
      <?php foreach ($holidays as $h): ?>
      <div class="flex items-center justify-between text-sm bg-ink-50 rounded-lg px-3 py-2">
        <span><?= jdate($h['gregorian_date'], 'Y/m/d') ?> — <?= e($h['jalali_label']) ?></span>
        <form method="post" action="<?= url('panel/settings/holidays/' . $h['id'] . '/remove') ?>">
          <?= csrf_field() ?>
          <button class="text-xs text-ink-400 hover:text-red-500">حذف</button>
        </form>
      </div>
      <?php endforeach; ?>
      <?php if (empty($holidays)): ?><p class="text-xs text-ink-400">تعطیلی ثبت نشده.</p><?php endif; ?>
    </div>
    <form method="post" action="<?= url('panel/settings/holidays') ?>" class="flex items-center gap-2">
      <?= csrf_field() ?>
      <input type="date" name="date" class="rounded-lg border border-ink-200 px-2 py-1.5 text-xs">
      <input type="text" name="label" placeholder="عنوان (مثلاً تاسوعا)" class="flex-1 rounded-lg border border-ink-200 px-2 py-1.5 text-xs">
      <button class="btn-ink h-9 text-xs px-3">افزودن دستی</button>
    </form>
    <p class="text-[11px] text-ink-400 mt-2">تعطیلات قمری (مثل عید فطر، تاسوعا و عاشورا) هر سال جابه‌جا می‌شوند و باید دستی اضافه شوند.</p>
  </div>
</div>
