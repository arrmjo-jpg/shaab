<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\VideoLibrary;

use App\Actions\Admin\VideoLibrary\BulkVideoAction;
use App\Actions\Admin\VideoLibrary\CreateVideoAction;
use App\Actions\Admin\VideoLibrary\DeleteVideoAction;
use App\Actions\Admin\VideoLibrary\ForceDeleteVideoAction;
use App\Actions\Admin\VideoLibrary\ListVideosAction;
use App\Actions\Admin\VideoLibrary\ReprocessVideoMediaAction;
use App\Actions\Admin\VideoLibrary\RestoreVideoAction;
use App\Actions\Admin\VideoLibrary\TransitionVideoStatusAction;
use App\Actions\Admin\VideoLibrary\UpdateVideoAction;
use App\Actions\Admin\VideoLibrary\VideoAnalyticsAction;
use App\Actions\Admin\VideoLibrary\VideoDashboardAction;
use App\Actions\Admin\VideoLibrary\VideoEntityAnalyticsAction;
use App\Actions\Admin\VideoLibrary\VideoOperationsAction;
use App\Actions\Admin\VideoLibrary\VideoStatsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\VideoLibrary\BulkVideoRequest;
use App\Http\Requests\Admin\VideoLibrary\StoreVideoRequest;
use App\Http\Requests\Admin\VideoLibrary\TransitionVideoRequest;
use App\Http\Requests\Admin\VideoLibrary\UpdateVideoRequest;
use App\Http\Resources\Admin\VideoLibrary\VideoResource;
use App\Models\Video;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VideoController extends Controller
{
    public function index(): JsonResponse
    {
        return (new ListVideosAction)->handle();
    }

    public function stats(): JsonResponse
    {
        return (new VideoStatsAction)->handle();
    }

    public function dashboard(): JsonResponse
    {
        return (new VideoDashboardAction)->handle();
    }

    public function analytics(): JsonResponse
    {
        return (new VideoAnalyticsAction)->handle();
    }

    public function operations(): JsonResponse
    {
        return (new VideoOperationsAction)->handle();
    }

    /** تحليلات فيديو واحد (سياقيّة) — نطاق زمني عبر range/from/to. */
    public function entityAnalytics(Request $request, Video $video): JsonResponse
    {
        return (new VideoEntityAnalyticsAction)->handle(
            $video,
            $request->query('range'),
            $request->query('from'),
            $request->query('to'),
        );
    }

    public function reprocess(Video $video): JsonResponse
    {
        return (new ReprocessVideoMediaAction)->handle($video);
    }

    public function bulk(BulkVideoRequest $request): JsonResponse
    {
        return (new BulkVideoAction)->handle($request->validated(), $request->user());
    }

    public function show(Video $video): JsonResponse
    {
        return ApiResponse::success(
            data: new VideoResource(
                $video->load(['author:id,name', 'mediaAsset', 'category', 'engagementCounter'])
            )
        );
    }

    public function store(StoreVideoRequest $request): JsonResponse
    {
        return (new CreateVideoAction)->handle($request->validated(), $request->user());
    }

    public function update(UpdateVideoRequest $request, Video $video): JsonResponse
    {
        return (new UpdateVideoAction)->handle($video, $request->validated(), $request->user());
    }

    public function status(TransitionVideoRequest $request, Video $video): JsonResponse
    {
        return (new TransitionVideoStatusAction)->handle($video, $request->validated(), $request->user());
    }

    public function destroy(Video $video): JsonResponse
    {
        return (new DeleteVideoAction)->handle($video);
    }

    public function restore(Video $video): JsonResponse
    {
        return (new RestoreVideoAction)->handle($video);
    }

    public function forceDelete(Video $video): JsonResponse
    {
        return (new ForceDeleteVideoAction)->handle($video);
    }
}
