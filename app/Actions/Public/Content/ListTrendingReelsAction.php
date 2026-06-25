<?php

declare(strict_types=1);

namespace App\Actions\Public\Content;

use App\Http\Resources\Public\Content\PublicReelResource;
use App\Models\Reel;
use App\Support\Cache\CachedRead;
use App\Support\Cache\CacheKeys;
use App\Support\Cache\CacheTtl;
use App\Support\Cache\ReelCacheTags;
use App\Support\Responses\ApiResponse;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * الريلز الرائجة — ترتيب حقيقي قائم على التفاعل (لا «الأحدث»). يربط جدول
 * engagement_counters الموحّد القائم ويرتّب بمعادلة موزونة:
 *
 *   score = views·1 + likes·4 + favorites·6 − dislikes·2
 *
 * المنطق: الحفظ (favorite) أقوى إشارة نيّة، يليه الإعجاب، ثم المشاهدة؛ وعدم
 * الإعجاب يخصم. كسر التعادل بالأحدث نشراً. لا عدّادات/تحليلات موازية — نفس
 * بنية التفاعل الموحّدة. (التعفية الزمنية المرجّحة تتطلّب إعادة حساب مجدوَلة
 * وهي خارج نطاق هذه المرحلة.)
 */
class ListTrendingReelsAction
{
    private const DEFAULT_LIMIT = 12;

    private const MAX_LIMIT = 50;

    public function handle(string $locale, Request $request): JsonResponse
    {
        if (! in_array($locale, Reel::LOCALES, true)) {
            return ApiResponse::error(__('article.invalid_locale'), [], 422);
        }

        $limit = max(1, min((int) $request->integer('per_page', self::DEFAULT_LIMIT), self::MAX_LIMIT));

        $data = CachedRead::remember(
            ReelCacheTags::feedTags($locale),
            CacheKeys::publicReelsTrending($locale, $limit),
            CacheTtl::REALTIME,
            fn (): array => PublicReelResource::collection($this->query($locale, $limit))->resolve()
        );

        return ApiResponse::success(data: $data);
    }

    /** @return Collection<int,Reel> */
    private function query(string $locale, int $limit)
    {
        $morph = (new Reel)->getMorphClass();
        $score = '(COALESCE(engagement_counters.views, 0) * 1'
            .' + COALESCE(engagement_counters.likes, 0) * 4'
            .' + COALESCE(engagement_counters.favorites, 0) * 6'
            .' - COALESCE(engagement_counters.dislikes, 0) * 2)';

        return Reel::query()
            ->published()
            ->forLocale($locale)
            ->leftJoin('engagement_counters', function ($join) use ($morph): void {
                $join->on('engagement_counters.engageable_id', '=', 'reels.id')
                    ->where('engagement_counters.engageable_type', '=', $morph);
            })
            ->select('reels.*')
            ->selectRaw("{$score} as trend_score")
            ->with(['mediaAsset', 'engagementCounter'])
            ->orderByDesc('trend_score')
            ->orderByDesc('reels.published_at')
            ->limit($limit)
            ->get();
    }
}
