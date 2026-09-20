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
  <div class="bg-white rounded-2xl border border-ink-100 p-5">
    <h2 class="text-sm font-bold text-ink-700 mb-4">مشخصات سالن</h2>
    <form method="post" action="<?= url('panel/settings/profile') ?>" class="space-y-3">
      <?= csrf_field() ?>
      <div>
        <label class="block text-xs text-ink-500 mb-1">نام سالن</label>
        <input type="text" name="name" value="<?= e($salon['name']) ?>" class="w-full rounded-xl border border-ink-200 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-xs text-ink-500 mb-1">شهر</label>
          <input type="text" name="city" value="<?= e($salon['city'] ?? '') ?>" class="w-full rounded-xl border border-ink-200 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
        </div>
        <div>
          <label class="block text-xs text-ink-500 mb-1">تلفن</label>
          <input type="text" dir="ltr" name="phone" value="<?= e($salon['phone'] ?? '') ?>" class="w-full rounded-xl border border-ink-200 px-3 py-2.5 text-sm text-left focus:outline-none focus:ring-2 focus:ring-brand-500">
        </div>
      </div>
      <div>
        <label class="block text-xs text-ink-500 mb-1">آدرس</label>
        <input type="text" name="address" value="<?= e($salon['address'] ?? '') ?>" class="w-full rounded-xl border border-ink-200 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
      </div>
      <div class="bg-ink-50 rounded-xl px-3 py-2.5 text-xs text-ink-500">
        لینک عمومی رزرو: <span dir="ltr" class="font-mono text-gold-700"><?= e(url('s/' . $salon['slug'])) ?></span>
      </div>
      <div class="flex items-center justify-between bg-ink-50 rounded-xl px-3 py-2.5 text-xs">
        <span class="text-ink-500">موجودی کیف پیامک</span>
        <span class="font-bold <?= (int)$salon['sms_credit'] <= 30 ? 'text-amber-600' : 'text-ink-700' ?>"><?= fa_num($salon['sms_credit']) ?> پیامک</span>
      </div>
      <button type="submit" class="w-full bg-ink-900 hover:bg-ink-800 text-white font-bold rounded-xl py-2.5 text-sm">ذخیره</button>
    </form>
  </div>

  <div class="bg-white rounded-2xl border border-ink-100 p-5">
    <h2 class="text-sm font-bold text-ink-700 mb-4">ساعت کاری سالن</h2>
    <form method="post" action="<?= url('panel/settings/hours') ?>" class="space-y-2">
      <?= csrf_field() ?>
      <?php foreach ($weekdayNames as $i => $name): $h = $hours[$i] ?? null; $closed = $h ? (bool)$h['is_closed'] : false; ?>
      <div class="flex items-center gap-2 text-sm">
        <span class="w-16 shrink-0 text-ink-600"><?= e($name) ?></span>
        <input type="time" name="opens_<?= $i ?>" value="<?= e(substr($h['opens_at'] ?? '09:00:00',0,5)) ?>" class="rounded-lg border border-ink-200 px-2 py-1.5 text-xs <?= $closed ? 'opacity-40' : '' ?>">
        <span class="text-ink-300">تا</span>
        <input type="time" name="closes_<?= $i ?>" value="<?= e(substr($h['closes_at'] ?? '21:00:00',0,5)) ?>" class="rounded-lg border border-ink-200 px-2 py-1.5 text-xs <?= $closed ? 'opacity-40' : '' ?>">
        <label class="flex items-center gap-1 text-xs text-ink-500 mr-auto">
          <input type="checkbox" name="closed_<?= $i ?>" <?= $closed ? 'checked' : '' ?>> تعطیل
        </label>
      </div>
      <?php endforeach; ?>
      <button type="submit" class="w-full mt-2 bg-ink-900 hover:bg-ink-800 text-white font-bold rounded-xl py-2.5 text-sm">ذخیره ساعت کاری</button>
    </form>
  </div>

  <div class="bg-white rounded-2xl border border-ink-100 p-5 lg:col-span-2">
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
      <button class="text-xs bg-ink-900 text-white hover:bg-ink-800 rounded-lg px-3 py-1.5">افزودن دستی</button>
    </form>
    <p class="text-[11px] text-ink-400 mt-2">تعطیلات قمری (مثل عید فطر، تاسوعا و عاشورا) هر سال جابه‌جا می‌شوند و باید دستی اضافه شوند.</p>
  </div>
</div>
