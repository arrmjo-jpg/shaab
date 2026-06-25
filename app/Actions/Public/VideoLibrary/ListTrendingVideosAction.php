<?php

declare(strict_types=1);

namespace App\Actions\Public\VideoLibrary;

use App\Http\Resources\Public\VideoLibrary\PublicVideoCardResource;
use App\Models\Video;
use App\Support\Cache\CachedRead;
use App\Support\Cache\CacheKeys;
use App\Support\Cache\CacheTtl;
use App\Support\Cache\VideoCacheTags;
use App\Support\Responses\ApiResponse;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * الفيديوهات الرائجة — ترتيب حقيقي قائم على التفاعل (لا «الأحدث»). يربط جدول
 * engagement_counters الموحّد ويرتّب بمعادلة موزونة (مطابقة للريلز):
 *
 *   score = views·1 + likes·4 + favorites·6 − dislikes·2
 *
 * الحفظ أقوى إشارة نيّة، يليه الإعجاب، ثم المشاهدة؛ وعدم الإعجاب يخصم. كسر التعادل
 * بالأحدث نشراً. عام + قابل للتشغيل فقط — لا تسريب وسائط غير جاهزة في الرائج.
 */
class ListTrendingVideosAction
{
    private const DEFAULT_LIMIT = 12;

    private const MAX_LIMIT = 50;

    public function handle(string $locale, Request $request): JsonResponse
    {
        if (! in_array($locale, Video::LOCALES, true)) {
            return ApiResponse::error(__('video.invalid_locale'), [], 422);
        }

        $limit = max(1, min((int) $request->integer('per_page', self::DEFAULT_LIMIT), self::MAX_LIMIT));

        $data = CachedRead::remember(
            VideoCacheTags::feedTags($locale),
            CacheKeys::publicVideosTrending($locale, $limit),
            CacheTtl::REALTIME,
            fn (): array => PublicVideoCardResource::collection($this->query($locale, $limit))->resolve()
        );

        return ApiResponse::success(data: $data);
    }

    /** @return Collection<int,Video> */
    private function query(string $locale, int $limit)
    {
        $morph = (new Video)->getMorphClass();
        $score = '(COALESCE(engagement_counters.views, 0) * 1'
            .' + COALESCE(engagement_counters.likes, 0) * 4'
            .' + COALESCE(engagement_counters.favorites, 0) * 6'
            .' - COALESCE(engagement_counters.dislikes, 0) * 2)';

        return Video::query()
            ->public()
            ->playable()
            ->forLocale($locale)
            ->leftJoin('engagement_counters', function ($join) use ($morph): void {
                $join->on('engagement_counters.engageable_id', '=', 'videos.id')
                    ->where('engagement_counters.engageable_type', '=', $morph);
            })
            ->select('videos.*')
            ->selectRaw("{$score} as trend_score")
            ->with(['mediaAsset', 'category', 'engagementCounter'])
            ->orderByDesc('trend_score')
            ->orderByDesc('videos.published_at')
            ->limit($limit)
            ->get();
    }
}
