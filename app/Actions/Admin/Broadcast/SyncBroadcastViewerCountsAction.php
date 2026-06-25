<?php

declare(strict_types=1);

namespace App\Actions\Admin\Broadcast;

use App\Enums\BroadcastStatus;
use App\Models\Broadcast;
use App\Models\BroadcastViewerSample;
use App\Support\Broadcast\BroadcastPresence;

/**
 * مزامنة لقطة عدّاد المشاهدين (viewer_count) من محرّك الحضور (الكاش) إلى قاعدة
 * البيانات — دفعة دورية (لا كتابة لكل نبضة؛ يُدار عبر SchedulerRegistry everyMinute).
 *
 * يُحدّث البثوث المباشرة فقط (المشاهدة الفعلية). التحديث عبر query builder بلا أحداث/
 * طوابع زمنية: لا تدقيق، لا إبطال كاش — لقطة تقريبية تتبع كاش B4 عند انتهاء صلاحيته.
 */
class SyncBroadcastViewerCountsAction
{
    public function handle(): int
    {
        $synced = 0;
        $now = now();
        $cutoff = $now->copy()->subDays(self::retentionDays());

        Broadcast::query()
            ->where('status', BroadcastStatus::Live->value)
            ->select(['id', 'viewer_count', 'peak_viewer_count'])
            ->chunkById(200, function ($broadcasts) use (&$synced, $now, $cutoff): void {
                foreach ($broadcasts as $broadcast) {
                    $count = BroadcastPresence::count((int) $broadcast->id);

                    // عيّنة تزامن دورية (تيليمتري المتوسّط/الذروة/منحنى التزامن).
                    BroadcastViewerSample::query()->create([
                        'broadcast_id' => $broadcast->id,
                        'viewers' => $count,
                        'sampled_at' => $now,
                    ]);

                    // لقطة العدّاد + ذروة كلّ الأزمنة (عمود دائم يتجاوز نافذة العيّنات).
                    $updates = [];
                    if ((int) $broadcast->viewer_count !== $count) {
                        $updates['viewer_count'] = $count;
                    }
                    if ($count > (int) $broadcast->peak_viewer_count) {
                        $updates['peak_viewer_count'] = $count;
                    }
                    if ($updates !== []) {
                        Broadcast::query()->whereKey($broadcast->id)->update($updates);
                    }

                    // تقليم النافذة المتدحرجة (حدّ النمو على القنوات الدائمة).
                    BroadcastViewerSample::query()
                        ->where('broadcast_id', $broadcast->id)
                        ->where('sampled_at', '<', $cutoff)
                        ->delete();

                    $synced++;
                }
            });

        return $synced;
    }

    private static function retentionDays(): int
    {
        return max(1, (int) config('broadcast.analytics.sample_retention_days', 30));
    }
}
