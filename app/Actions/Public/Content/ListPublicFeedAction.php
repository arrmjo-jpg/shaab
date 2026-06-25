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
 * Public feed resolver — one of:
 *
 *   - `latest`   → latest published articles in locale (published_at desc)
 *   - زون عرض (hero/breaking/header/editors_pick) → مدفوعة بعلم منطقي على الخبر
 *     نفسه، لا جدول تنسيبات منفصل.
 *
 * مصدر الحقيقة الوحيد لمكان العرض هو أعلام جدول الأخبار:
 *   hero=is_featured · breaking=is_breaking · header=is_header · editors_pick=is_editor_pick
 * المحرّر يضبط العلم من نموذج الخبر مباشرةً — لا «تنسيبات تحريرية» منفصلة.
 * داخل كل زون: المثبَّت (is_pinned) أولاً ثمّ الأحدث نشراً.
 */
class ListPublicFeedAction
{
    /** Maximum items per feed (defensive ceiling — prevents pathological limits). */
    private const MAX_LIMIT = 50;

    /** زون العرض ← علم الخبر المقابل (مصدر الحقيقة). */
    public const ZONE_FLAGS = [
        'hero' => 'is_featured',
        'breaking' => 'is_breaking',
        'header' => 'is_header',
        'editors_pick' => 'is_editor_pick',
    ];

    /** Default per-zone limits when caller omits ?limit=. */
    public const DEFAULT_LIMITS = [
        'hero' => 5,
        'breaking' => 10,
        'header' => 5,
        'editors_pick' => 8,
        'latest' => 12,
    ];

    public function handle(string $locale, string $kind, ?int $limit = null): JsonResponse
    {
        if (! in_array($locale, Article::LOCALES, true)) {
            return ApiResponse::error(__('article.invalid_locale'), [], 422);
        }

        if (! in_array($kind, self::allowedKinds(), true)) {
            return ApiResponse::error(__('article.invalid_feed'), [], 422);
        }

        $effective = max(1, min(self::MAX_LIMIT, $limit ?? self::DEFAULT_LIMITS[$kind] ?? 5));

        $payload = CachedRead::remember(
            [ArticleCacheTags::ALL, ArticleCacheTags::feed($locale)],
            CacheKeys::publicFeed($locale, $kind, $effective),
            CacheTtl::SHORT,
            fn (): array => $this->build($locale, $kind, $effective),
        );

        return ApiResponse::success(data: $payload);
    }

    /** @return array<int,array<string,mixed>> */
    private function build(string $locale, string $kind, int $limit): array
    {
        $articles = $kind === 'latest'
            ? $this->byFlag($locale, null, $limit)
            : $this->byFlag($locale, self::ZONE_FLAGS[$kind], $limit);

        return PublicArticleListItemResource::collection($articles)->resolve();
    }

    /**
     * مقالات منشورة في اللغة، مُقيَّدة بعلم زون اختياري، مرتّبة: المثبَّت أولاً
     * ثمّ الأحدث. flag=null → الأحدث عموماً (latest).
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

    /** @return array<int,string> */
    public static function allowedKinds(): array
    {
        return [...array_keys(self::ZONE_FLAGS), 'latest'];
    }
}
