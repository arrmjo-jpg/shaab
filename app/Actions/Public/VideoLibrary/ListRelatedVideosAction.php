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
 * فيديوهات ذات صلة («شاهد أيضاً» للجوّال) — نفس تصنيف الفيديو المصدر متى وُجد، وإلا
 * أحدث الفيديوهات العامة في اللغة. عام + قابل للتشغيل فقط، يُستبعد الفيديو نفسه.
 * المصدر يجب أن يكون قابلاً للعرض؛ سلَغ غير موجود ⇒ 404 (يُستدعى من صفحة التفاصيل).
 */
class ListRelatedVideosAction
{
    private const DEFAULT_LIMIT = 8;

    private const MAX_LIMIT = 24;

    public function handle(string $locale, string $slug, Request $request): JsonResponse
    {
        if (! in_array($locale, Video::LOCALES, true)) {
            return ApiResponse::error(__('video.invalid_locale'), [], 422);
        }

        $source = Video::query()
            ->viewable()
            ->forLocale($locale)
            ->where('slug', $slug)
            ->first(['id', 'video_category_id', 'locale', 'slug']);

        if ($source === null) {
            return ApiResponse::error(__('video.not_found'), [], 404);
        }

        $limit = max(1, min((int) $request->integer('per_page', self::DEFAULT_LIMIT), self::MAX_LIMIT));

        $data = CachedRead::remember(
            VideoCacheTags::feedTags($locale),
            CacheKeys::publicRelatedVideos($locale, $slug, $limit),
            CacheTtl::REALTIME,
            fn (): array => PublicVideoCardResource::collection(
                $this->query($locale, (int) $source->id, $source->video_category_id, $limit)
            )->resolve()
        );

        return ApiResponse::success(data: $data);
    }

    private function query(string $locale, int $excludeId, ?int $categoryId, int $limit)
    {
        return Video::query()
            ->public()
            ->playable()
            ->forLocale($locale)
            ->whereKeyNot($excludeId)
            ->when($categoryId !== null, fn ($q) => $q->where('video_category_id', $categoryId))
            ->with(['mediaAsset', 'category', 'engagementCounter'])
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get();
    }
}
