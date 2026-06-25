<?php

declare(strict_types=1);

namespace App\Actions\Admin\VideoLibrary;

use App\Http\Resources\Admin\VideoLibrary\VideoPlaylistResource;
use App\Models\Video;
use App\Models\VideoPlaylist;
use App\Support\Cache\VideoCacheTags;
use App\Support\Frontend\FrontendCacheTags;
use App\Support\Frontend\FrontendRevalidate;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class DetachPlaylistVideoAction
{
    public function handle(VideoPlaylist $playlist, Video $video): JsonResponse
    {
        $playlist->videos()->detach($video->id);

        $tags = VideoCacheTags::playlistInvalidationTags($playlist);
        Cache::tags($tags)->flush();
        FrontendRevalidate::tags(FrontendCacheTags::fromVideoTags($tags));

        return ApiResponse::success(
            __('video_playlist.video_detached'),
            new VideoPlaylistResource($playlist->fresh()->load('videos')->loadCount('videos'))
        );
    }
}
