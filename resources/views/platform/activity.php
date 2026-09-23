<?php
/**
 * گزارش فعالیت مدیریتی.
 *
 * هر کاری که از پنل پلتفرم روی دادهٔ یک سالن اثر گذاشته، اینجاست.
 * این صفحه برای وقتی است که کسی می‌پرسد «چرا پلن ما عوض شد؟» —
 * و جواب باید از روی داده بیاید، نه از حافظه.
 *
 * @var array $logs
 */
$active = 'activity';
include __DIR__ . '/_nav.php';
?>

<h1 class="page-title mb-4">گزارش فعالیت</h1>

<?php if ($logs === []): ?>
  <div class="glass rounded-2xl py-12 text-center">
    <p class="text-sm text-ink-500">هنوز کاری ثبت نشده.</p>
  </div>
<?php else: ?>
  <div class="glass rounded-2xl divide-y" style="border-color:var(--line)">
    <?php foreach ($logs as $log): ?>
      <?php $meta = $log['meta_json'] !== null ? json_decode((string) $log['meta_json'], true) : null; ?>
      <div class="px-4 py-3">
        <div class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
          <span class="text-[12px] text-ink-400 tabular-nums shrink-0">
            <?= e(jdate($log['created_at'], 'Y/m/d H:i')) ?>
          </span>
          <span class="text-[13px] font-bold text-ink-900">
            <?= e(App\Domain\Platform\AuditLog::label((string) $log['action'])) ?>
          </span>
          <?php if ($log['salon_name'] !== null): ?>
            <a href="<?= e(url('platform/' . $log['salon_id'])) ?>"
               class="tap inline-flex items-center min-h-[44px] -my-3 max-w-full
                      text-[12px] text-accent truncate">— <?= e($log['salon_name']) ?></a>
          <?php endif; ?>
        </div>

        <p class="text-[12px] text-ink-400 mt-0.5">
          <?= e($log['actor_name'] ?? 'سیستم') ?>
          <span class="code" dir="ltr"><?= e(fa_num($log['actor_phone'] ?? '')) ?></span>
          <?php if (is_array($meta) && $meta !== []): ?>
            <span class="text-ink-400">·</span>
            <?php foreach ($meta as $k => $v): ?>
              <span class="code"><?= e((string) $k) ?>=<?= e(is_scalar($v) ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE)) ?></span>
            <?php endforeach; ?>
          <?php endif; ?>
        </p>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
