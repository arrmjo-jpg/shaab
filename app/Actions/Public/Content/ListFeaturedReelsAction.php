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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * الريلز المميَّزة — تستخدم علم is_featured الحقيقي (لا ترتيب وهمي). منشور فقط.
 */
class ListFeaturedReelsAction
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
            CacheKeys::publicReelsFeatured($locale, $limit),
            CacheTtl::REALTIME,
            fn (): array => PublicReelResource::collection(
                Reel::query()
                    ->published()
                    ->forLocale($locale)
                    ->where('is_featured', true)
                    ->with(['mediaAsset', 'engagementCounter'])
                    ->orderByDesc('published_at')
                    ->limit($limit)
                    ->get()
            )->resolve()
        );

        return ApiResponse::success(data: $data);
    }
}
