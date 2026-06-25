<?php

declare(strict_types=1);

namespace App\Actions\Admin\VideoLibrary;

use App\Http\Resources\Admin\VideoLibrary\VideoPlaylistResource;
use App\Models\VideoPlaylist;
use App\Support\Cache\VideoCacheTags;
use App\Support\Frontend\FrontendCacheTags;
use App\Support\Frontend\FrontendRevalidate;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * إعادة ترتيب فيديوهات قائمة تشغيل (سحب) عبر position في الـ pivot — مرآة
 * ReorderArticleMediaAction. تُقبَل فقط الفيديوهات المُسنَدة فعلاً (تُتجاهَل الدخيلة).
 */
class ReorderPlaylistVideosAction
{
    /** @param  array<int,int>  $orderedIds */
    public function handle(VideoPlaylist $playlist, array $orderedIds): JsonResponse
    {
        $validIds = $playlist->videos()->pluck('videos.id')->flip();

        $position = 0;
        DB::transaction(function () use ($playlist, $orderedIds, $validIds, &$position): void {
            foreach ($orderedIds as $id) {
                if ($validIds->has((int) $id)) {
                    DB::table('playlist_video')
                        ->where('video_playlist_id', $playlist->id)
                        ->where('video_id', (int) $id)
                        ->update(['position' => $position++]);
                }
            }
        });

        $tags = VideoCacheTags::playlistInvalidationTags($playlist);
        Cache::tags($tags)->flush();
        FrontendRevalidate::tags(FrontendCacheTags::fromVideoTags($tags));

        return ApiResponse::success(
            __('video_playlist.reordered'),
            new VideoPlaylistResource($playlist->fresh()->load('videos')->loadCount('videos'))
        );
    }
}
