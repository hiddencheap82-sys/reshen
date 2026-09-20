<?php
/**
 * فهرست الگوهای پیامک — برای کپی کردن در پنل اپراتور.
 *
 * @var array $rows
 * @var string $driver
 * @var bool $dedicatedLine
 */
$configured = 0;
foreach ($rows as $r) {
    if ($r['melipayamak'] !== '' || $r['kavenegar'] !== '') { $configured++; }
}
$total = count($rows);
?>

<h1 class="page-title mb-1">الگوهای پیامک</h1>
<p class="text-[12px] text-ink-400 mb-5 leading-relaxed">
  روی خط خدماتی (۳۰۰۰، ۲۰۰۰، ۹۸۲۱) اپراتور پیامکِ متنِ آزاد را تحویل نمی‌دهد.
  متن باید از پیش در سامانهٔ الگوی ملی ثبت و تأیید شده باشد و فقط متغیرهایش پر شود.
</p>

<div class="glass rounded-2xl p-4 mb-5 <?= $configured === $total ? '' : 'hairline-accent' ?>">
  <div class="flex items-center gap-3">
    <span class="w-10 h-10 shrink-0 rounded-xl grid place-items-center"
          style="background:var(--accent-soft);color:var(--accent)" aria-hidden="true">
      <?= icon($configured === $total ? 'check' : 'alert', 'w-5 h-5') ?>
    </span>
    <div class="min-w-0">
      <p class="text-[13px] font-bold text-ink-900">
        <?= e(fa_num($configured)) ?> از <?= e(fa_num($total)) ?> الگو تنظیم شده
      </p>
      <p class="text-[11px] text-ink-400 mt-0.5">
        درایور فعلی: <span class="code"><?= e($driver) ?></span>
        <?php if ($driver === 'log'): ?>
          — حالت توسعه، پیامک واقعی فرستاده نمی‌شود
        <?php endif; ?>
      </p>
    </div>
  </div>

  <?php if ($dedicatedLine): ?>
    <p class="text-[11px] text-ink-500 mt-3 pt-3 leading-relaxed" style="border-top:1px solid var(--line)">
      خط اختصاصی روشن است: الگویی که تنظیم نشده باشد، با متن آزاد فرستاده می‌شود.
    </p>
  <?php else: ?>
    <p class="text-[11px] text-ink-500 mt-3 pt-3 leading-relaxed" style="border-top:1px solid var(--line)">
      الگویی که تنظیم نشده باشد، <strong class="text-ink-700">فرستاده نمی‌شود</strong> —
      به‌جای اینکه بی‌صدا به مقصد نرسد.
    </p>
  <?php endif; ?>
</div>

<div class="space-y-3">
  <?php foreach ($rows as $code => $r):
      $ready = $r['melipayamak'] !== '' || $r['kavenegar'] !== '';
  ?>
    <section class="glass rounded-2xl overflow-hidden">
      <div class="flex items-center gap-2.5 px-4 py-3 border-b" style="border-color:var(--line)">
        <span class="w-2 h-2 rounded-full shrink-0"
              style="background:<?= $ready ? '#059669' : 'var(--accent)' ?>"
              aria-hidden="true"></span>
        <h2 class="card-title flex-1 min-w-0"><?= e($r['title']) ?></h2>
        <?php if ($r['critical']): ?>
          <span class="text-[10px] font-bold rounded-full px-2 py-0.5 text-accent"
                style="background:var(--accent-soft)">حیاتی</span>
        <?php endif; ?>
        <span class="text-[11px] <?= $ready ? 'text-green-700' : 'text-ink-400' ?> font-bold shrink-0">
          <?= $ready ? 'تنظیم شده' : 'تنظیم نشده' ?>
        </span>
      </div>

      <div class="p-4 space-y-3">
        <p class="text-[11px] text-ink-400 leading-relaxed"><?= e($r['note']) ?></p>

        <div>
          <div class="flex items-center justify-between mb-1.5">
            <span class="text-[11px] font-bold text-ink-500">متنی که باید ثبت کنی</span>
            <button type="button" class="copy-btn h-9 px-3 rounded-lg text-[11px] font-bold text-accent
                                         hover:bg-ink-100 transition-colors cursor-pointer"
                    data-copy="<?= e($r['pattern']) ?>">کپی</button>
          </div>
          <pre class="text-[12px] leading-relaxed rounded-xl px-3 py-2.5 whitespace-pre-wrap
                      text-ink-800" style="background:var(--accent-soft)"><?= e($r['pattern']) ?></pre>
        </div>

        <div>
          <span class="block text-[11px] font-bold text-ink-500 mb-1.5">
            ترتیب متغیرها <span class="font-normal text-ink-400">— اپراتور با شماره می‌شناسدشان</span>
          </span>
          <ol class="flex flex-wrap gap-1.5">
            <?php foreach ($r['vars'] as $i => $v): ?>
              <li class="text-[11px] rounded-lg px-2 py-1 bg-ink-100 text-ink-700">
                <span class="tabular-nums text-ink-400"><?= e(fa_num($i + 1)) ?>.</span>
                <span class="code"><?= e($v) ?></span>
              </li>
            <?php endforeach; ?>
          </ol>
        </div>

        <div>
          <span class="block text-[11px] font-bold text-ink-500 mb-1.5">شناسه را اینجا بگذار</span>
          <code class="code block text-[11px] rounded-xl px-3 py-2 bg-ink-100 text-ink-700"><?= e($r['envKey']) ?>=…</code>
          <p class="text-[10.5px] text-ink-400 mt-1.5">در فایل <span class="code">.env</span> کنار بقیهٔ تنظیمات.</p>
        </div>
      </div>
    </section>
  <?php endforeach; ?>
</div>

<script>
(function () {
  document.querySelectorAll('.copy-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var text = btn.getAttribute('data-copy');
      var done = function () {
        var old = btn.textContent;
        btn.textContent = 'کپی شد';
        setTimeout(function () { btn.textContent = old; }, 1800);
      };
      // در اتصال بدون HTTPS، clipboard در دسترس نیست — راه برگشتی
      if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(done);
        return;
      }
      var ta = document.createElement('textarea');
      ta.value = text;
      ta.style.position = 'fixed';
      ta.style.opacity = '0';
      document.body.appendChild(ta);
      ta.select();
      try { document.execCommand('copy'); done(); } catch (e) {}
      ta.remove();
    });
  });
})();
</script>
