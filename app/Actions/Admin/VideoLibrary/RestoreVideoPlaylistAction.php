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

class RestoreVideoPlaylistAction
{
    public function handle(VideoPlaylist $playlist): JsonResponse
    {
        if (! $playlist->trashed()) {
            return ApiResponse::error(__('video_playlist.not_deleted'), [], 422);
        }

        $playlist->restore();
        $tags = VideoCacheTags::playlistInvalidationTags($playlist);
        Cache::tags($tags)->flush();
        FrontendRevalidate::tags(FrontendCacheTags::fromVideoTags($tags));

        return ApiResponse::success(
            __('video_playlist.restored'),
            new VideoPlaylistResource($playlist->fresh())
        );
    }
}
