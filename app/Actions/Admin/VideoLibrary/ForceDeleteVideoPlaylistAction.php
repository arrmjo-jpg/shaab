<?php

declare(strict_types=1);

namespace App\Actions\Admin\VideoLibrary;

use App\Models\VideoPlaylist;
use App\Support\Cache\VideoCacheTags;
use App\Support\Frontend\FrontendCacheTags;
use App\Support\Frontend\FrontendRevalidate;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * حذف نهائي لقائمة تشغيل. روابط playlist_video تُحذَف عبر cascade؛ الفيديوهات
 * نفسها لا تُمَسّ.
 */
class ForceDeleteVideoPlaylistAction
{
    public function handle(VideoPlaylist $playlist): JsonResponse
    {
        $playlist->forceDelete();

        $tags = VideoCacheTags::playlistInvalidationTags($playlist);
        Cache::tags($tags)->flush();
        FrontendRevalidate::tags(FrontendCacheTags::fromVideoTags($tags));

        return ApiResponse::success(__('video_playlist.force_deleted'));
    }
}
