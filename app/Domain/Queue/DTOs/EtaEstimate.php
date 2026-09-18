<?php

declare(strict_types=1);

namespace App\Domain\Queue\DTOs;

use Carbon\CarbonImmutable;

/**
 * تخمین زمان نشستن روی صندلی.
 *
 * همیشه یک «بازه» است، نه یک عدد. این عمدی است:
 * «۱۸:۰۰» وعده‌ای است که شکسته می‌شود؛ «۱۸:۰۰ تا ۱۸:۱۵» وعده‌ای است
 * که تقریباً همیشه درست از آب درمی‌آید. دقت را قربانی می‌کنیم تا اعتماد بخریم.
 */
final readonly class EtaEstimate
{
    public function __construct(
        /** صدک ۵۰ — کران پایین بازه. */
        public CarbonImmutable $minAt,

        /** صدک ۸۰ — کران بالای بازه. */
        public CarbonImmutable $maxAt,

        /** high | medium | low — بر اساس تعداد نمونهٔ موجود. */
        public string $confidence,

        /** تعداد نفرات جلوتر در صف. */
        public int $aheadCount,
    ) {}

    public function minMinutesFromNow(CarbonImmutable $now): int
    {
        return max(0, (int) round($now->diffInMinutes($this->minAt, absolute: false)));
    }

    public function maxMinutesFromNow(CarbonImmutable $now): int
    {
        return max(0, (int) round($now->diffInMinutes($this->maxAt, absolute: false)));
    }

    /**
     * متن فارسی آماده برای نمایش و پیامک.
     *
     * قواعد نمایش در docs/10-architecture/04-queue-eta-engine.md §۴ آمده است.
     * نکتهٔ مهم: بازهٔ بزرگ‌تر از max_display_range_minutes به مشتری هیچ نمی‌گوید
     * و فقط بی‌اعتمادی می‌سازد — پس محدود می‌شود.
     */
    public function label(CarbonImmutable $now): string
    {
        // پیاده‌سازی در فاز ۱ — به Support\Jalali و فرمت‌کنندهٔ اعداد فارسی وابسته است.
        // tests/Unit/Domain/Queue/EtaEstimateTest.php قواعد را تثبیت می‌کند.
        throw new \LogicException('پیاده‌نشده — استوری ۴٫۴ در بک‌لاگ.');
    }
}
