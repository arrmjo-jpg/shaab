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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * الفيديوهات المميَّزة — علم is_featured الحقيقي (لا ترتيب وهمي). عام + قابل للتشغيل.
 */
class ListFeaturedVideosAction
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
            CacheKeys::publicVideosFeatured($locale, $limit),
            CacheTtl::REALTIME,
            fn (): array => PublicVideoCardResource::collection(
                Video::query()
                    ->public()
                    ->playable()
                    ->forLocale($locale)
                    ->where('is_featured', true)
                    ->with(['mediaAsset', 'category', 'engagementCounter'])
                    ->orderByDesc('published_at')
                    ->limit($limit)
                    ->get()
            )->resolve()
        );

        return ApiResponse::success(data: $data);
    }
}
