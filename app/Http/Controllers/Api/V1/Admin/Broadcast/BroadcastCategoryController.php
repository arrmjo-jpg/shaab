<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Broadcast;

use App\Actions\Admin\Broadcast\CreateBroadcastCategoryAction;
use App\Actions\Admin\Broadcast\DeleteBroadcastCategoryAction;
use App\Actions\Admin\Broadcast\ListBroadcastCategoriesAction;
use App\Actions\Admin\Broadcast\UpdateBroadcastCategoryAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Broadcast\StoreBroadcastCategoryRequest;
use App\Http\Requests\Admin\Broadcast\UpdateBroadcastCategoryRequest;
use App\Http\Resources\Admin\Broadcast\BroadcastCategoryResource;
use App\Models\BroadcastCategory;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class BroadcastCategoryController extends Controller
{
    public function index(): JsonResponse
    {
        return (new ListBroadcastCategoriesAction)->handle();
    }

    public function show(BroadcastCategory $broadcastCategory): JsonResponse
    {
        return ApiResponse::success(
            data: new BroadcastCategoryResource($broadcastCategory->loadCount('broadcasts')->load('cover'))
        );
    }

    public function store(StoreBroadcastCategoryRequest $request): JsonResponse
    {
        return (new CreateBroadcastCategoryAction)->handle($request->validated());
    }

    public function update(UpdateBroadcastCategoryRequest $request, BroadcastCategory $broadcastCategory): JsonResponse
    {
        return (new UpdateBroadcastCategoryAction)->handle($broadcastCategory, $request->validated());
    }

    public function destroy(BroadcastCategory $broadcastCategory): JsonResponse
    {
        return (new DeleteBroadcastCategoryAction)->handle($broadcastCategory);
    }
}
