<?php

declare(strict_types=1);

namespace App\Support\Epaper;

use App\Models\EpaperArchiveSearchDaily;
use App\Models\EpaperDailyStat;
use App\Models\EpaperIssueStat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * مُسجِّل استخدامٍ خفيف لحدثين منخفضي/متوسّطي التواتر يُكمّلان أنبوب التحليلات:
 *  - التنزيلات (نادرة — مستحقّة): عدّاد تراكميّ + يوميّ لكل عدد.
 *  - بحث الأرشيف العابر (مُخنوق): عدّاد يوميّ لكل لغة.
 *
 * زيادات ذرّية مباشرة (أخفّ من وظيفة طابور لحدثٍ تافه) وأفضل-جهد: أيّ تعذّر يُسجَّل
 * ويُبتلَع فلا يكسر التنزيل/البحث أبداً. جلسات القراءة عالية الحجم تبقى مُطابَرة
 * (RecordEpaperReadingSessionJob). بلا هوية مستخدم/IP (واعٍ للخصوصية).
 */
final class EpaperUsageRecorder
{
    public static function recordDownload(int $epaperId): void
    {
        try {
            DB::transaction(function () use ($epaperId): void {
                EpaperIssueStat::query()->firstOrCreate(['epaper_id' => $epaperId])->increment('downloads');
                EpaperDailyStat::query()
                    ->firstOrCreate(['epaper_id' => $epaperId, 'stat_date' => now()->toDateString()])
                    ->increment('downloads');
            });
        } catch (Throwable $e) {
            Log::warning('epaper.analytics.download_failed', ['epaper_id' => $epaperId, 'error' => $e->getMessage()]);
        }
    }

    public static function recordArchiveSearch(string $locale): void
    {
        try {
            EpaperArchiveSearchDaily::query()
                ->firstOrCreate(['stat_date' => now()->toDateString(), 'locale' => $locale])
                ->increment('count');
        } catch (Throwable $e) {
            Log::warning('epaper.analytics.archive_search_failed', ['locale' => $locale, 'error' => $e->getMessage()]);
        }
    }
}
