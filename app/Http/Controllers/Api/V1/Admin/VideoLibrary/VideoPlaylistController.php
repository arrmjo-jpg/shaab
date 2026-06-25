<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\VideoLibrary;

use App\Actions\Admin\VideoLibrary\AttachPlaylistVideosAction;
use App\Actions\Admin\VideoLibrary\CreateVideoPlaylistAction;
use App\Actions\Admin\VideoLibrary\DeleteVideoPlaylistAction;
use App\Actions\Admin\VideoLibrary\DetachPlaylistVideoAction;
use App\Actions\Admin\VideoLibrary\ForceDeleteVideoPlaylistAction;
use App\Actions\Admin\VideoLibrary\ListVideoPlaylistsAction;
use App\Actions\Admin\VideoLibrary\ReorderPlaylistVideosAction;
use App\Actions\Admin\VideoLibrary\RestoreVideoPlaylistAction;
use App\Actions\Admin\VideoLibrary\UpdateVideoPlaylistAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\VideoLibrary\AttachPlaylistVideosRequest;
use App\Http\Requests\Admin\VideoLibrary\ReorderPlaylistVideosRequest;
use App\Http\Requests\Admin\VideoLibrary\StoreVideoPlaylistRequest;
use App\Http\Requests\Admin\VideoLibrary\UpdateVideoPlaylistRequest;
use App\Http\Resources\Admin\VideoLibrary\VideoPlaylistResource;
use App\Models\Video;
use App\Models\VideoPlaylist;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class VideoPlaylistController extends Controller
{
    /** سقف الفيديوهات المُحمَّلة في المحرّر — يمنع حمولة/ذاكرة مرضية لقوائم ضخمة. */
    private const MAX_EDITOR_VIDEOS = 500;

    public function index(): JsonResponse
    {
        return (new ListVideoPlaylistsAction)->handle();
    }

    public function show(VideoPlaylist $videoPlaylist): JsonResponse
    {
        // videos مرتّبة بـ position (في علاقة النموذج) ومحدودة بسقف — videos_count
        // يبقى الإجمالي الحقيقي عبر loadCount. قائمة واحدة ⇒ limit مسطّح صحيح.
        $videoPlaylist
            ->load([
                'author:id,name',
                'cover',
                'videos' => fn ($q) => $q->with('mediaAsset')->limit(self::MAX_EDITOR_VIDEOS),
            ])
            ->loadCount('videos');

        return ApiResponse::success(data: new VideoPlaylistResource($videoPlaylist));
    }

    public function store(StoreVideoPlaylistRequest $request): JsonResponse
    {
        return (new CreateVideoPlaylistAction)->handle($request->validated(), $request->user());
    }

    public function update(UpdateVideoPlaylistRequest $request, VideoPlaylist $videoPlaylist): JsonResponse
    {
        return (new UpdateVideoPlaylistAction)->handle($videoPlaylist, $request->validated());
    }

    public function destroy(VideoPlaylist $videoPlaylist): JsonResponse
    {
        return (new DeleteVideoPlaylistAction)->handle($videoPlaylist);
    }

    public function restore(VideoPlaylist $videoPlaylist): JsonResponse
    {
        return (new RestoreVideoPlaylistAction)->handle($videoPlaylist);
    }

    public function forceDelete(VideoPlaylist $videoPlaylist): JsonResponse
    {
        return (new ForceDeleteVideoPlaylistAction)->handle($videoPlaylist);
    }

    public function attachVideos(AttachPlaylistVideosRequest $request, VideoPlaylist $videoPlaylist): JsonResponse
    {
        return (new AttachPlaylistVideosAction)->handle($videoPlaylist, $request->validated('video_ids'));
    }

    public function detachVideo(VideoPlaylist $videoPlaylist, Video $video): JsonResponse
    {
        return (new DetachPlaylistVideoAction)->handle($videoPlaylist, $video);
    }

    public function reorderVideos(ReorderPlaylistVideosRequest $request, VideoPlaylist $videoPlaylist): JsonResponse
    {
        return (new ReorderPlaylistVideosAction)->handle($videoPlaylist, $request->validated('ordered_ids'));
    }
}
