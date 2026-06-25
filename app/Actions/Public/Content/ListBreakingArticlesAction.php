<?php

declare(strict_types=1);

namespace App\Actions\Public\Content;

use App\Models\Article;
use App\Models\MediaAsset;
use App\Support\Cache\ArticleCacheTags;
use App\Support\Cache\CachedRead;
use App\Support\Cache\CacheKeys;
use App\Support\Cache\CacheTtl;
use App\Support\Content\CdnTtl;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * المسار السريع للأخبار العاجلة (breaking fast lane) — ticker خفيف يُستهلَك بتردّد
 * عالٍ من الواجهة/الجوّال. حمولة دقيقة جداً (لا محتوى/علاقات ثقيلة)، single-flight
 * ضدّ العاصفة، TTL حافة قصير جداً (CdnTtl::breaking) لطزاجة فورية بلا إحراج تحريري.
 *
 * المصدر: علم is_breaking (إشارة «عاجل الآن» المُدارة عبر ClearBreakingArticlesAction)
 * — منفصل عن منطقة placements «breaking» التحريرية للصفحة الرئيسية. يُوسَم feed(locale)
 * فيُبطَل عند أي كتابة مقال في اللغة أو عند مسح العاجل.
 */
class ListBreakingArticlesAction
{
    private const LIMIT = 10;

    public function handle(string $locale): JsonResponse
    {
        if (! in_array($locale, Article::LOCALES, true)) {
            return ApiResponse::error(__('article.invalid_locale'), [], 422);
        }

        $payload = CachedRead::remember(
            ArticleCacheTags::feedTags($locale),
            CacheKeys::make('public', 'breaking', $locale),
            CacheTtl::SHORT,
            fn (): array => $this->build($locale),
        );

        return ApiResponse::success(data: $payload)
            ->header('Cache-Control', CdnTtl::breaking());
    }

    /** @return array<int,array<string,mixed>> */
    private function build(string $locale): array
    {
        return Article::query()
            ->published()
            ->forLocale($locale)
            ->where('is_breaking', true)
            ->with(['mediaAssets' => fn ($q) => $q->wherePivot('collection', 'cover')])
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit(self::LIMIT)
            ->get(['id', 'locale', 'title', 'slug', 'published_at'])
            ->map(fn (Article $a): array => [
                'id' => $a->id,
                'title' => $a->title,
                'slug' => $a->slug,
                'canonical_path' => $a->canonicalPath(),
                'published_at' => $a->published_at?->toISOString(),
                'cover_thumb' => $a->mediaAssets
                    ->first(fn (MediaAsset $m): bool => $m->pivot->collection === 'cover')
                    ?->conversionUrl('thumb'),
            ])
            ->all();
    }
}
