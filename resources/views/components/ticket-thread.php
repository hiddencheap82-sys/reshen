<?php
/**
 * رشتهٔ گفتگوی یک تیکت — همان مؤلفه در هر دو پنل.
 *
 * چرا مشترک: پشتیبان و صاحب سالن باید *دقیقاً* یک چیز ببینند. اگر دو
 * ویوِ جدا باشند، از هفتهٔ سوم با هم فرق می‌کنند و آن‌وقت پشتیبان
 * چیزی را توضیح می‌دهد که طرف مقابل نمی‌بیند.
 *
 * تنها فرقشان طرفِ «خودی» است: پیامِ خودت راست‌چین و رنگی، پیامِ طرف
 * مقابل خنثی.
 *
 * @var array $messages
 * @var string $mySide 'salon' یا 'platform'
 */
$names = ['salon' => 'سالن', 'platform' => 'پشتیبانی'];
?>

<div class="space-y-2.5">
  <?php foreach ($messages as $m): ?>
    <?php $mine = $m['side'] === $mySide; ?>
    <div class="flex <?= $mine ? 'justify-start' : 'justify-end' ?>">
      <div class="max-w-[85%] rounded-2xl px-3.5 py-2.5
                  <?= $mine ? 'bg-accent-soft' : 'glass' ?>">
        <div class="flex items-baseline gap-2 mb-1">
          <span class="text-[11px] font-bold <?= $mine ? 'text-accent' : 'text-ink-500' ?>">
            <?= e($names[$m['side']] ?? $m['side']) ?>
            <?php if (($m['user_name'] ?? '') !== ''): ?>
              <span class="font-normal">· <?= e($m['user_name']) ?></span>
            <?php endif; ?>
          </span>
          <span class="text-[11px] text-ink-400 tabular-nums">
            <?= e(jdate((string) $m['created_at'], 'Y/m/d H:i')) ?>
          </span>
        </div>
        <!-- nl2br و نه ویرایشگر غنی: متن ساده امن است و تیکت پشتیبانی
             به بولد و لینک نیازی ندارد. -->
        <p class="text-[13px] text-ink-800 leading-relaxed whitespace-pre-line"><?= e($m['body']) ?></p>
      </div>
    </div>
  <?php endforeach; ?>
</div>
