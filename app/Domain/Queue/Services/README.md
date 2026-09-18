# سرویس‌های دامنهٔ صف

| کلاس | مسئولیت |
|---|---|
| `QueueOrderer` | ترتیب صف یک صندلی — پیاده‌سازی شده (اسکلت) |
| `DurationEstimator` | تخمین مدت یک نوبت از `duration_stats` + ضریب مشتری |
| `EtaCalculator` | ساخت `EtaEstimate` برای هر نفر در صف |
| `QueueSnapshotBuilder` | ترکیب همه در یک `QueueSnapshot` قابل کش |

**قاعده:** این‌ها فقط **محاسبه** می‌کنند و چیزی را تغییر نمی‌دهند. هر تغییر حالت در `Actions/` است.

مشخصات کامل: [docs/10-architecture/04-queue-eta-engine.md](../../../../docs/10-architecture/04-queue-eta-engine.md)
