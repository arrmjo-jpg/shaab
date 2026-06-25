<?php

declare(strict_types=1);

namespace App\Actions\Public\Content;

use App\Http\Resources\Public\Content\PublicArticleListItemResource;
use App\Models\Article;
use App\Support\Cache\ArticleCacheTags;
use App\Support\Cache\CachedRead;
use App\Support\Cache\CacheKeys;
use App\Support\Cache\CacheTtl;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

/**
 * Homepage aggregate — every flag-driven zone + latest, in a single round-trip.
 *
 * مصدر الحقيقة هو أعلام جدول الأخبار (لا تنسيبات): hero=is_featured،
 * breaking=is_breaking، header=is_header، editors_pick=is_editor_pick. داخل كل
 * زون: المثبَّت أولاً ثمّ الأحدث. كاش قصير + إبطال عبر وسم feed عند أي كتابة خبر.
 */
class BuildPublicHomepageAction
{
    public function handle(string $locale): JsonResponse
    {
        if (! in_array($locale, Article::LOCALES, true)) {
            return ApiResponse::error(__('article.invalid_locale'), [], 422);
        }

        $payload = CachedRead::remember(
            [ArticleCacheTags::ALL, ArticleCacheTags::feed($locale)],
            CacheKeys::publicHomepage($locale),
            CacheTtl::SHORT,
            fn (): array => $this->build($locale),
        );

        return ApiResponse::success(data: $payload);
    }

    /** @return array<string,array<int,array<string,mixed>>> */
    private function build(string $locale): array
    {
        $limits = ListPublicFeedAction::DEFAULT_LIMITS;

        $shape = [];
        foreach (ListPublicFeedAction::ZONE_FLAGS as $zone => $flag) {
            $shape[$zone] = PublicArticleListItemResource::collection(
                $this->byFlag($locale, $flag, $limits[$zone] ?? 5)
            )->resolve();
        }
        $shape['latest'] = PublicArticleListItemResource::collection(
            $this->byFlag($locale, null, $limits['latest'])
        )->resolve();

        return $shape;
    }

    /**
     * مقالات منشورة بعلم زون اختياري، المثبَّت أولاً ثمّ الأحدث.
     *
     * @return Collection<int,Article>
     */
    private function byFlag(string $locale, ?string $flag, int $limit): Collection
    {
        return Article::query()
            ->published()
            ->forLocale($locale)
            ->when($flag !== null, fn ($q) => $q->where($flag, true))
            ->with(['author:id,name,avatar,is_writer', 'primaryCategory:id,name,slug', 'mediaAssets' => fn ($q) => $q->wherePivot('collection', 'cover')])
            ->orderByDesc('is_pinned')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }
}
