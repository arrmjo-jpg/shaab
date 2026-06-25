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

class DeleteVideoPlaylistAction
{
    public function handle(VideoPlaylist $playlist): JsonResponse
    {
        $playlist->delete();

        $tags = VideoCacheTags::playlistInvalidationTags($playlist);
        Cache::tags($tags)->flush();
        FrontendRevalidate::tags(FrontendCacheTags::fromVideoTags($tags));

        return ApiResponse::success(__('video_playlist.deleted'));
    }
}
