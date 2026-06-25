<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\VideoLibrary;

use App\Actions\Admin\VideoLibrary\CreateVideoCategoryAction;
use App\Actions\Admin\VideoLibrary\DeleteVideoCategoryAction;
use App\Actions\Admin\VideoLibrary\ForceDeleteVideoCategoryAction;
use App\Actions\Admin\VideoLibrary\ListVideoCategoriesAction;
use App\Actions\Admin\VideoLibrary\MoveVideoCategoryAction;
use App\Actions\Admin\VideoLibrary\RestoreVideoCategoryAction;
use App\Actions\Admin\VideoLibrary\UpdateVideoCategoryAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\VideoLibrary\MoveVideoCategoryRequest;
use App\Http\Requests\Admin\VideoLibrary\StoreVideoCategoryRequest;
use App\Http\Requests\Admin\VideoLibrary\UpdateVideoCategoryRequest;
use App\Http\Resources\Admin\VideoLibrary\VideoCategoryResource;
use App\Models\VideoCategory;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class VideoCategoryController extends Controller
{
    public function index(): JsonResponse
    {
        return (new ListVideoCategoriesAction)->handle();
    }

    public function show(VideoCategory $videoCategory): JsonResponse
    {
        return ApiResponse::success(
            data: new VideoCategoryResource($videoCategory->loadCount('videos')->load('cover'))
        );
    }

    public function store(StoreVideoCategoryRequest $request): JsonResponse
    {
        return (new CreateVideoCategoryAction)->handle($request->validated());
    }

    public function update(UpdateVideoCategoryRequest $request, VideoCategory $videoCategory): JsonResponse
    {
        return (new UpdateVideoCategoryAction)->handle($videoCategory, $request->validated());
    }

    public function move(MoveVideoCategoryRequest $request, VideoCategory $videoCategory): JsonResponse
    {
        return (new MoveVideoCategoryAction)->handle($videoCategory, $request->validated('direction'));
    }

    public function destroy(VideoCategory $videoCategory): JsonResponse
    {
        return (new DeleteVideoCategoryAction)->handle($videoCategory);
    }

    public function restore(VideoCategory $videoCategory): JsonResponse
    {
        return (new RestoreVideoCategoryAction)->handle($videoCategory);
    }

    public function forceDelete(VideoCategory $videoCategory): JsonResponse
    {
        return (new ForceDeleteVideoCategoryAction)->handle($videoCategory);
    }
}
