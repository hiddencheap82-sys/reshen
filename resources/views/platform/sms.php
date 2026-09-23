<?php
/**
 * مصرف پیامک.
 *
 * پیامک تنها هزینهٔ متغیر ماست. دو چیز اینجا مهم است: کدام سالن
 * پرمصرف است (برای صورتحساب)، و چه خطاهایی تکرار می‌شوند (برای
 * اینکه بفهمیم کجا چیزی خراب است).
 *
 * @var array $usage
 * @var array $failures
 */
$active = 'sms';
include __DIR__ . '/_nav.php';
?>

<h1 class="page-title mb-1">مصرف پیامک</h1>
<p class="text-[12px] text-ink-400 mb-5">۳۰ روز گذشته</p>

<?php if ($failures !== []): ?>
  <!--
    خطاها بالاتر از مصرف می‌آیند: مصرف فقط عدد است، ولی خطای تکرارشونده
    یعنی چیزی همین حالا خراب است — مثلاً الگویی که تأیید نشده و هر
    یادآوری‌اش رد می‌شود.
  -->
  <section class="glass rounded-2xl p-4 mb-5 hairline-accent">
    <h2 class="card-title mb-3">خطاهای تکرارشونده</h2>
    <ul class="space-y-2.5">
      <?php foreach ($failures as $f): ?>
        <li>
          <div class="flex items-baseline gap-2">
            <span class="code text-[12px] text-ink-500 shrink-0"><?= e($f['template_code']) ?></span>
            <span class="text-[13px] font-bold text-ink-800 tabular-nums"><?= e(fa_num((int) $f['count'])) ?> بار</span>
            <span class="ms-auto text-[12px] text-ink-400 tabular-nums shrink-0">
              <?= e(jdate($f['last_at'], 'Y/m/d')) ?>
            </span>
          </div>
          <p class="text-[12px] text-ink-500 mt-0.5 leading-relaxed">
            <?= e($f['error_message'] ?? 'بدون پیام') ?>
          </p>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
<?php endif; ?>

<?php if ($usage === []): ?>
  <div class="glass rounded-2xl py-12 text-center">
    <p class="text-sm text-ink-500">در ۳۰ روز گذشته پیامکی فرستاده نشده.</p>
  </div>
<?php else: ?>
  <div class="glass rounded-2xl divide-y" style="border-color:var(--line)">
    <?php foreach ($usage as $row): ?>
      <a href="<?= e(url('platform/' . $row['id'])) ?>"
         class="tap flex items-center gap-3 px-4 py-3.5 hover:bg-ink-50">
        <span class="flex-1 min-w-0 font-bold text-ink-900 truncate"><?= e($row['name']) ?></span>
        <span class="text-left shrink-0">
          <span class="block text-[15px] font-extrabold text-ink-800 tabular-nums">
            <?= e(fa_num((int) $row['total'])) ?>
          </span>
          <span class="block text-[12px] tabular-nums
                       <?= (int) $row['failed'] > 0 ? 'text-bad' : 'text-ink-400' ?>">
            <?= (int) $row['failed'] > 0
                ? e(fa_num((int) $row['failed'])) . ' ناموفق'
                : 'همه رسید' ?>
          </span>
        </span>
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
