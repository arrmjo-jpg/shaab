<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Content;

use App\Actions\Admin\Content\CreateLiveUpdateAction;
use App\Actions\Admin\Content\DeleteLiveUpdateAction;
use App\Actions\Admin\Content\ListLiveUpdatesAction;
use App\Actions\Admin\Content\MoveLiveUpdateAction;
use App\Actions\Admin\Content\UpdateLiveUpdateAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Content\MoveLiveUpdateRequest;
use App\Http\Requests\Admin\Content\StoreLiveUpdateRequest;
use App\Http\Requests\Admin\Content\UpdateLiveUpdateRequest;
use App\Models\Article;
use App\Models\ArticleLiveUpdate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LiveUpdateController extends Controller
{
    public function index(Article $article): JsonResponse
    {
        return (new ListLiveUpdatesAction)->handle($article);
    }

    public function store(StoreLiveUpdateRequest $request, Article $article): JsonResponse
    {
        return (new CreateLiveUpdateAction)->handle($article, $request->validated(), $request->user());
    }

    public function update(
        UpdateLiveUpdateRequest $request,
        Article $article,
        ArticleLiveUpdate $liveUpdate
    ): JsonResponse {
        return (new UpdateLiveUpdateAction)->handle(
            $article,
            $liveUpdate,
            $request->validated(),
            $request->user(),
        );
    }

    public function move(
        MoveLiveUpdateRequest $request,
        Article $article,
        ArticleLiveUpdate $liveUpdate
    ): JsonResponse {
        return (new MoveLiveUpdateAction)->handle(
            $article,
            $liveUpdate,
            $request->validated('direction'),
            $request->user(),
        );
    }

    public function destroy(Request $request, Article $article, ArticleLiveUpdate $liveUpdate): JsonResponse
    {
        return (new DeleteLiveUpdateAction)->handle($article, $liveUpdate, $request->user());
    }
}
