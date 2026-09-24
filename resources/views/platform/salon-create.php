<?php
/**
 * ساخت سالن از پنل پلتفرم.
 *
 * فرم عمداً کوتاه است: هر فیلدی که اینجا اضافه شود، یک دلیل بیشتر
 * برای اینکه راه‌اندازی مشتری نصفه بماند. بقیه‌اش را صاحب سالن خودش
 * از تنظیمات پر می‌کند.
 *
 * @var array $plans
 */
$active = 'salons';
include __DIR__ . '/_nav.php';
?>

<div class="flex items-center gap-2 mb-5">
  <a href="<?= e(url('platform/salons')) ?>"
     class="w-11 h-11 grid place-items-center rounded-xl text-ink-500 hover:bg-ink-100 tap shrink-0"
     aria-label="بازگشت به سالن‌ها"><?= icon('chevron-start', 'w-4 h-4') ?></a>
  <div class="min-w-0">
    <h1 class="page-title">سالن تازه</h1>
    <p class="text-[12px] text-ink-400 mt-0.5">سالن و حساب صاحبش با هم ساخته می‌شوند.</p>
  </div>
</div>

<form method="post" action="<?= e(url('platform/salons')) ?>" class="max-w-xl space-y-4">
  <?= csrf_field() ?>

  <section class="glass rounded-2xl p-4 sm:p-5 space-y-3.5">
    <h2 class="card-title">سالن</h2>

    <div>
      <label for="name" class="block text-[12px] font-bold text-ink-600 mb-1.5">نام سالن</label>
      <input type="text" name="name" id="name" required maxlength="150"
             placeholder="آرایشگاه پدیده" class="field">
      <p class="text-[12px] text-ink-400 mt-1.5">
        آدرس عمومی از روی همین ساخته می‌شود. اگر تکراری باشد، عدد می‌گیرد.
      </p>
    </div>

    <div class="grid sm:grid-cols-2 gap-3">
      <div>
        <label for="city" class="block text-[12px] font-bold text-ink-600 mb-1.5">شهر</label>
        <input type="text" name="city" id="city" maxlength="80" placeholder="تهران" class="field">
      </div>
      <div>
        <label for="seats" class="block text-[12px] font-bold text-ink-600 mb-1.5">تعداد صندلی</label>
        <input type="text" name="seats" id="seats" value="۱" autocomplete="off"
               inputmode="numeric" class="field tabular-nums">
      </div>
    </div>

    <div>
      <label for="address" class="block text-[12px] font-bold text-ink-600 mb-1.5">
        نشانی <span class="font-normal text-ink-400">(اختیاری)</span>
      </label>
      <input type="text" name="address" id="address" maxlength="255" class="field">
    </div>

    <div>
      <label for="plan_code" class="block text-[12px] font-bold text-ink-600 mb-1.5">پلن</label>
      <select name="plan_code" id="plan_code" class="field">
        <?php foreach ($plans as $plan): ?>
          <option value="<?= e($plan['code']) ?>" <?= $plan['code'] === 'trial' ? 'selected' : '' ?>>
            <?= e($plan['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <p class="text-[12px] text-ink-400 mt-1.5">
        روی «آزمایشی»، مهلت سی روزه خودش گذاشته می‌شود.
      </p>
    </div>
  </section>

  <section class="glass rounded-2xl p-4 sm:p-5 space-y-3.5">
    <h2 class="card-title">صاحب سالن</h2>
    <p class="text-[12px] text-ink-400 -mt-1.5 leading-relaxed">
      اگر این شماره از قبل حساب داشته باشد، همان حساب به سالن وصل می‌شود.
    </p>

    <div>
      <label for="owner_phone" class="block text-[12px] font-bold text-ink-600 mb-1.5">
        شمارهٔ موبایل
      </label>
      <input type="tel" name="owner_phone" id="owner_phone" required inputmode="numeric" dir="ltr"
             placeholder="۰۹۱۲۳۴۵۶۷۸۹" class="field text-left tabular-nums">
    </div>

    <div>
      <label for="owner_name" class="block text-[12px] font-bold text-ink-600 mb-1.5">
        نام <span class="font-normal text-ink-400">(اختیاری)</span>
      </label>
      <input type="text" name="owner_name" id="owner_name" maxlength="120" class="field">
    </div>

    <div>
      <label for="owner_password" class="block text-[12px] font-bold text-ink-600 mb-1.5">
        رمز عبور <span class="font-normal text-ink-400">(اختیاری)</span>
      </label>
      <input type="text" name="owner_password" id="owner_password" autocomplete="off" class="field">
      <p class="text-[12px] text-ink-400 mt-1.5 leading-relaxed">
        خالی بگذارید تا با کد پیامکی وارد شود. اگر پر کنید، رمز را خودتان
        شفاهی به او بدهید — بعداً نمایش داده نمی‌شود.
      </p>
    </div>
  </section>

  <button type="submit" class="btn-accent metal w-full">ساخت سالن</button>
</form>
