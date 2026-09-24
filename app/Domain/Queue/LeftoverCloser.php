<?php

declare(strict_types=1);

namespace App\Domain\Queue;

use App\Core\DB;

/**
 * هرچه از روزهای قبل در صف مانده، بسته می‌شود.
 *
 * آرایشگر آخر شب خسته است و گاهی دکمه‌ها را نمی‌زند. پیش‌تر این دو
 * حالت هیچ‌وقت درست نمی‌شدند:
 *
 * ۱. **مشتریِ روی صندلیِ دیروز.** کسی که دیشب اصلاح شد و «تمام شد»
 *    نخورد، تا ابد روی صندلی می‌ماند. صندلی اشغال دیده می‌شد، پس نفر
 *    بعدیِ امروز هیچ‌وقت خودکار نمی‌نشست، و پنل آرایشگر صبح با کسی
 *    شروع می‌شد که دوازده ساعت پیش رفته بود. کارِ پاک‌سازیِ قبلی فقط
 *    «در صف» و «تأییدشده» را می‌بست — «روی صندلی» را نه.
 *
 * ۲. **حضوریِ دیروزی که هرگز نوبتش نشد.** با آستانهٔ ۲۴ ساعته، مشتریِ
 *    ساعت ۹ شبِ دیروز تا ساعت ۹ شبِ امروز اول صف می‌نشست و تخمینِ
 *    همه را عقب می‌برد.
 *
 * قاعدهٔ تازه: «از روزی پیش از امروز، و دست‌کم شش ساعت پیش». شرط
 * دوم برای سالنی است که تا بعد از نیمه‌شب باز است — مشتریِ ساعت
 * ۲۳:۵۰ نباید ساعت ۰۰:۱۰ «غیبت» بخورد در حالی که روی صندلی نشسته.
 *
 * صندلیِ دیروز «انجام‌شده» بسته می‌شود نه «غیبت» — اصلاح تقریباً
 * حتماً انجام شده، فقط دکمه‌اش زده نشده. زمانِ پایانش همین لحظه ثبت
 * می‌شود تا در «نیاز به رسیدگی»ِ داشبورد امروز به‌عنوان «تمام‌شده،
 * تسویه‌نشده» بیاید و صاحب سالن پولش را ثبت کند — نه اینکه بی‌صدا گم
 * شود. نمونهٔ مدت‌زمان هم ثبت نمی‌شود (این کلاس DurationLearner را صدا
 * نمی‌زند)، چون مدتِ واقعی را نمی‌دانیم.
 */
final class LeftoverCloser
{
    /** @return array{no_show:int,completed:int} */
    public function run(): array
    {
        $noShow = DB::statement(
            "UPDATE appointments
                SET status = 'no_show', updated_at = NOW()
              WHERE status IN ('queued', 'confirmed')
                AND DATE(COALESCE(scheduled_at, queued_at, created_at)) < CURDATE()
                AND COALESCE(scheduled_at, queued_at, created_at) < (NOW() - INTERVAL 6 HOUR)"
        )->rowCount();

        $completed = DB::statement(
            "UPDATE appointments
                SET status = 'completed', actual_end_at = NOW(), updated_at = NOW()
              WHERE status = 'in_chair'
                AND DATE(COALESCE(actual_start_at, queued_at, scheduled_at, created_at)) < CURDATE()
                AND COALESCE(actual_start_at, queued_at, scheduled_at, created_at) < (NOW() - INTERVAL 6 HOUR)"
        )->rowCount();

        return ['no_show' => $noShow, 'completed' => $completed];
    }
}
