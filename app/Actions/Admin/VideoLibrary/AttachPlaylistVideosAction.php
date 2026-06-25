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
use Illuminate\Support\Facades\DB;

/**
 * إضافة فيديوهات إلى قائمة تشغيل — تُلحَق في النهاية بترتيب صريح (position) بعد
 * أكبر موضع حالي. تُتجاهَل الفيديوهات المضافة مسبقاً (idempotent) وغير الموجودة.
 */
class AttachPlaylistVideosAction
{
    /** @param  array<int,int>  $videoIds */
    public function handle(VideoPlaylist $playlist, array $videoIds): JsonResponse
    {
        $ids = array_map('intval', $videoIds);

        DB::transaction(function () use ($playlist, $ids): void {
            $existing = $playlist->videos()->pluck('videos.id')->flip();
            // وجود دفعةً واحدة (استعلام واحد) بدل whereKey لكل عنصر — يمنع N استعلام.
            $valid = Video::query()->whereIn('id', $ids)->pluck('id')->flip();
            $position = (int) DB::table('playlist_video')
                ->where('video_playlist_id', $playlist->id)
                ->max('position');

            foreach ($ids as $id) {
                if ($existing->has($id) || ! $valid->has($id)) {
                    continue; // مُضاف مسبقاً أو غير موجود
                }
                $playlist->videos()->attach($id, ['position' => ++$position]);
                $existing->put($id, true);
            }
        });

        $tags = VideoCacheTags::playlistInvalidationTags($playlist);
        Cache::tags($tags)->flush();
        FrontendRevalidate::tags(FrontendCacheTags::fromVideoTags($tags));

        return ApiResponse::success(
            __('video_playlist.videos_attached'),
            new VideoPlaylistResource($playlist->fresh()->load('videos')->loadCount('videos'))
        );
    }
}
