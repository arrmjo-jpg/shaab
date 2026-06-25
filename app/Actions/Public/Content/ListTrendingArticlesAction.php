<?php

declare(strict_types=1);

namespace App\Actions\Public\Content;

use App\Http\Resources\Public\Content\PublicArticleListItemResource;
use App\Models\Article;
use App\Support\Cache\ArticleCacheTags;
use App\Support\Cache\CachedRead;
use App\Support\Cache\CacheKeys;
use App\Support\Cache\CacheTtl;
use App\Support\Content\CdnTtl;
use App\Support\Responses\ApiResponse;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * المقالات الرائجة — ترتيب حقيقي موزون بالتفاعل (نفس معادلة الريلز):
 *
 *   score = views·1 + likes·4 + favorites·6 − dislikes·2
 *
 * يُقصَر على نافذة حديثة (افتراضي 7 أيام) كي يعكس «الرائج الآن» لا الأرشيف. يربط
 * engagement_counters الموحّد (لا تحليلات موازية). كاش feed(locale) قصير (REALTIME).
 */
class ListTrendingArticlesAction
{
    private const DEFAULT_LIMIT = 10;

    private const MAX_LIMIT = 50;

    private const WINDOW_DAYS = 7;

    public function handle(string $locale, Request $request): JsonResponse
    {
        if (! in_array($locale, Article::LOCALES, true)) {
            return ApiResponse::error(__('article.invalid_locale'), [], 422);
        }

        $limit = max(1, min((int) $request->integer('per_page', self::DEFAULT_LIMIT), self::MAX_LIMIT));

        $data = CachedRead::remember(
            ArticleCacheTags::feedTags($locale),
            CacheKeys::publicArticlesTrending($locale, $limit),
            CacheTtl::REALTIME,
            fn (): array => PublicArticleListItemResource::collection($this->query($locale, $limit))->resolve(),
        );

        return ApiResponse::success(data: $data)
            ->header('Cache-Control', CdnTtl::breaking());
    }

    /** @return Collection<int,Article> */
    private function query(string $locale, int $limit)
    {
        $morph = (new Article)->getMorphClass();
        $score = '(COALESCE(engagement_counters.views, 0) * 1'
            .' + COALESCE(engagement_counters.likes, 0) * 4'
            .' + COALESCE(engagement_counters.favorites, 0) * 6'
            .' - COALESCE(engagement_counters.dislikes, 0) * 2)';

        return Article::query()
            ->published()
            ->forLocale($locale)
            ->where('published_at', '>=', now()->subDays(self::WINDOW_DAYS))
            ->leftJoin('engagement_counters', function ($join) use ($morph): void {
                $join->on('engagement_counters.engageable_id', '=', 'articles.id')
                    ->where('engagement_counters.engageable_type', '=', $morph);
            })
            ->select('articles.*')
            ->selectRaw("{$score} as trend_score")
            ->with(['author:id,name', 'primaryCategory:id,name,slug', 'mediaAssets' => fn ($q) => $q->wherePivot('collection', 'cover')])
            ->orderByDesc('trend_score')
            ->orderByDesc('articles.published_at')
            ->limit($limit)
            ->get();
    }
}
