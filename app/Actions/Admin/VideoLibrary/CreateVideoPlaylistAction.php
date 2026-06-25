<?php

declare(strict_types=1);

namespace App\Actions\Admin\VideoLibrary;

use App\Enums\VideoStatus;
use App\Enums\VideoVisibility;
use App\Http\Resources\Admin\VideoLibrary\VideoPlaylistResource;
use App\Models\User;
use App\Models\VideoPlaylist;
use App\Support\Cache\VideoCacheTags;
use App\Support\Frontend\FrontendCacheTags;
use App\Support\Frontend\FrontendRevalidate;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * إنشاء قائمة تشغيل (مسودّة افتراضياً). الفيديوهات تُضاف لاحقاً عبر attach/reorder.
 */
class CreateVideoPlaylistAction
{
    public function handle(array $validated, User $actor): JsonResponse
    {
        $playlist = new VideoPlaylist;
        $playlist->fill([
            'author_id' => $validated['author_id'] ?? $actor->id,
            'locale' => $validated['locale'],
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'cover_media_id' => $validated['cover_media_id'] ?? null,
            'status' => $validated['status'] ?? VideoStatus::Draft->value,
            'visibility' => $validated['visibility'] ?? VideoVisibility::Public->value,
            'is_featured' => $validated['is_featured'] ?? false,
            'sort_order' => $validated['sort_order'] ?? 0,
            'seo_title' => $validated['seo_title'] ?? null,
            'seo_description' => $validated['seo_description'] ?? null,
            'seo_keywords' => $validated['seo_keywords'] ?? null,
            'canonical_url' => $validated['canonical_url'] ?? null,
            'robots' => $validated['robots'] ?? null,
        ]);

        if (! empty($validated['slug'])) {
            $playlist->slug = $validated['slug'];
        }

        if (($playlist->status === VideoStatus::Published) && $playlist->published_at === null) {
            $playlist->published_at = now();
        }

        $playlist->save();

        $tags = VideoCacheTags::playlistInvalidationTags($playlist);
        Cache::tags($tags)->flush();
        FrontendRevalidate::tags(FrontendCacheTags::fromVideoTags($tags));

        return ApiResponse::success(
            __('video_playlist.created'),
            new VideoPlaylistResource($playlist),
            201
        );
    }
}
