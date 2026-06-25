<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Content;

use App\Actions\Admin\Content\CreatePageAction;
use App\Actions\Admin\Content\DeletePageAction;
use App\Actions\Admin\Content\ForceDeletePageAction;
use App\Actions\Admin\Content\ListPagesAction;
use App\Actions\Admin\Content\RestorePageAction;
use App\Actions\Admin\Content\TransitionPageStatusAction;
use App\Actions\Admin\Content\UpdatePageAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Content\StorePageRequest;
use App\Http\Requests\Admin\Content\TransitionPageRequest;
use App\Http\Requests\Admin\Content\UpdatePageRequest;
use App\Http\Resources\Admin\Content\PageResource;
use App\Models\Page;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class PageController extends Controller
{
    public function index(): JsonResponse
    {
        return (new ListPagesAction)->handle();
    }

    public function show(Page $page): JsonResponse
    {
        return ApiResponse::success(
            data: new PageResource($page->load('author:id,name'))
        );
    }

    public function store(StorePageRequest $request): JsonResponse
    {
        return (new CreatePageAction)->handle($request->validated(), $request->user());
    }

    public function update(UpdatePageRequest $request, Page $page): JsonResponse
    {
        return (new UpdatePageAction)->handle($page, $request->validated(), $request->user());
    }

    public function status(TransitionPageRequest $request, Page $page): JsonResponse
    {
        return (new TransitionPageStatusAction)->handle($page, $request->validated(), $request->user());
    }

    public function destroy(Page $page): JsonResponse
    {
        return (new DeletePageAction)->handle($page);
    }

    public function restore(Page $page): JsonResponse
    {
        return (new RestorePageAction)->handle($page);
    }

    public function forceDelete(Page $page): JsonResponse
    {
        return (new ForceDeletePageAction)->handle($page);
    }
}
