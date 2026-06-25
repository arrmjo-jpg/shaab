<?php

declare(strict_types=1);

namespace App\Actions\Admin\VideoLibrary;

use App\Enums\EngagementType;
use App\Models\Broadcast;
use App\Models\Video;
use App\Support\Analytics\AnalyticsRange;
use App\Support\Analytics\DailyEngagementReader;
use App\Support\Cache\CacheTtl;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * تحليلات فيديو واحد (سياقيّة، داخل النطاق) — مقاييس حقيقية فقط:
 *
 *  • التفاعل: مجاميع تراكمية (engagement_counters) + المتفاعلون الفريدون (لقطة) + المعدّل.
 *  • السلاسل الزمنية: المشاهدات/التفاعل اليوميّ من content_daily_stats (إلى-الأمام فقط،
 *    منذ بدء التتبّع) — لا أثر رجعيّ، لا أرقام وهمية.
 *  • مصادر الزيارات: تفصيل خشن للقناة من نفس التجميع اليوميّ.
 *  • التوزيع/SEO/النشر: بيانات وصفية حقيقية (تمييز/تصنيف/قوائم/VOD مرتبط/تاريخ المسارات/
 *    سجلّ النشر من التدقيق).
 *  • مقاييس المشاهدة (بدء/متوسّط مشاهدة/إكمال/تسرّب): مؤجّلة بصدق — لا تيليمتري مشغّل بعد.
 */
class VideoEntityAnalyticsAction
{
    public function handle(Video $video, ?string $range, ?string $from = null, ?string $to = null): JsonResponse
    {
        $window = AnalyticsRange::resolve($range, $from, $to);

        $data = Cache::remember(
            "video:analytics:entity:{$video->id}:{$window->key()}:v1",
            CacheTtl::SHORT,
            fn (): array => $this->compute($video, $window),
        );

        return ApiResponse::success(data: $data);
    }

    /** @return array<string,mixed> */
    private function compute(Video $video, AnalyticsRange $window): array
    {
        $video->loadMissing(['category', 'mediaAsset', 'engagementCounter']);
        $morph = $video->getMorphClass();

        $metrics = $video->engagementMetrics();
        $series = DailyEngagementReader::read($morph, $video->id, $window);

        return [
            'entity' => [
                'id' => $video->id,
                'title' => $video->title,
                'slug' => $video->slug,
                'locale' => $video->locale,
                'status' => $video->status->value,
                'visibility' => $video->visibility->value,
                'is_featured' => (bool) $video->is_featured,
                'duration_seconds' => (int) $video->duration_seconds,
                'created_at' => $video->created_at?->toISOString(),
            ],
            'engagement' => [
                'views' => $metrics['views'],
                'likes' => $metrics['likes'],
                'dislikes' => $metrics['dislikes'],
                'favorites' => $metrics['favorites'],
                'unique_reactors' => $this->uniqueReactors($morph, $video->id),
                'engagement_rate' => $this->engagementRate($metrics),
            ],
            'trend' => [
                'window' => $window->toArray(),
                'forward_only' => true,
                'points' => $series['points'],
                'totals' => $series['totals'],
            ],
            'traffic' => [
                'forward_only' => true,
                'total' => array_sum($series['channels']),
                'channels' => $series['channels'],
            ],
            'distribution' => [
                'is_featured' => (bool) $video->is_featured,
                'category' => $video->category === null ? null : [
                    'id' => $video->category->id,
                    'name' => $video->category->name,
                    'slug' => $video->category->slug,
                ],
                'playlists' => $video->playlists()
                    ->limit(50)
                    ->get(['video_playlists.id', 'title', 'slug'])
                    ->map(fn ($p): array => ['id' => $p->id, 'title' => $p->title, 'slug' => $p->slug])
                    ->all(),
                'linked_vods' => Broadcast::query()
                    ->where('vod_video_id', $video->id)
                    ->limit(50)
                    ->get(['id', 'title', 'slug', 'kind', 'status'])
                    ->map(fn (Broadcast $b): array => [
                        'id' => $b->id,
                        'title' => $b->title,
                        'slug' => $b->slug,
                        'kind' => $b->kind->value,
                        'status' => $b->status->value,
                    ])->all(),
            ],
            'seo' => [
                'slug' => $video->slug,
                'locale' => $video->locale,
                'canonical_path' => $video->canonicalPath(),
                'redirect_history' => $video->urlHistory()
                    ->orderByDesc('id')
                    ->limit(20)
                    ->get(['old_path', 'locale', 'reason', 'created_at'])
                    ->map(fn ($h): array => [
                        'old_path' => $h->old_path,
                        'locale' => $h->locale,
                        'reason' => $h->reason,
                        'at' => $h->created_at?->toISOString(),
                    ])->all(),
            ],
            'publishing' => [
                'status' => $video->status->value,
                'visibility' => $video->visibility->value,
                'published_at' => $video->published_at?->toISOString(),
                'is_scheduled' => $video->published_at !== null && $video->published_at->isFuture(),
                'created_at' => $video->created_at?->toISOString(),
                'timeline' => $this->publishTimeline($video),
            ],
            // مؤجّل بصدق: لا تيليمتري مشغّل (heartbeat/تقدّم) لمحتوى الطلب — يتطلّب
            // خطّ ابتلاع منارة مشغّل جديد (مرحلة مستقلّة)؛ لا نزيّف أرقاماً.
            'watch' => [
                'available' => false,
                'reason' => 'not_tracked',
            ],
        ];
    }

    private function uniqueReactors(string $morph, int $id): int
    {
        return (int) DB::table('engagements')
            ->where('engageable_type', $morph)
            ->where('engageable_id', $id)
            ->whereIn('type', [
                EngagementType::Like->value,
                EngagementType::Dislike->value,
                EngagementType::Favorite->value,
            ])
            ->distinct()
            ->count('actor_key');
    }

    /** @param array{views:int,likes:int,dislikes:int,favorites:int} $m */
    private function engagementRate(array $m): float
    {
        if ($m['views'] <= 0) {
            return 0.0;
        }

        return round((($m['likes'] + $m['dislikes'] + $m['favorites']) / $m['views']) * 100, 2);
    }

    /**
     * سجلّ النشر/الجدولة الحقيقي من التدقيق (Spatie activitylog، log_name=video) — تغيّرات
     * الحالة/الرؤية/تاريخ النشر فقط. القيم في عمود properties ({attributes, old}).
     *
     * @return list<array<string,mixed>>
     */
    private function publishTimeline(Video $video): array
    {
        $tracked = ['status', 'visibility', 'published_at'];

        return DB::table('activity_log')
            ->where('log_name', 'video')
            ->where('subject_id', $video->id)
            ->orderByDesc('id')
            ->limit(15)
            ->get(['event', 'properties', 'created_at'])
            ->map(function ($row) use ($tracked): ?array {
                $props = json_decode((string) $row->properties, true) ?: [];
                $attrs = is_array($props['attributes'] ?? null) ? $props['attributes'] : [];
                $old = is_array($props['old'] ?? null) ? $props['old'] : [];

                $changes = [];
                foreach ($tracked as $field) {
                    if (array_key_exists($field, $attrs)) {
                        $changes[] = ['field' => $field, 'from' => $old[$field] ?? null, 'to' => $attrs[$field]];
                    }
                }
                if ($changes === []) {
                    return null;
                }

                return ['event' => $row->event, 'at' => $row->created_at, 'changes' => $changes];
            })
            ->filter()
            ->values()
            ->all();
    }
}
