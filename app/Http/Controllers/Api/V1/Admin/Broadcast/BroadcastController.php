<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Broadcast;

use App\Actions\Admin\Broadcast\BroadcastDashboardAction;
use App\Actions\Admin\Broadcast\BroadcastEntityAnalyticsAction;
use App\Actions\Admin\Broadcast\CreateBroadcastAction;
use App\Actions\Admin\Broadcast\DeleteBroadcastAction;
use App\Actions\Admin\Broadcast\ListBroadcastsAction;
use App\Actions\Admin\Broadcast\UpdateBroadcastAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Broadcast\StoreBroadcastRequest;
use App\Http\Requests\Admin\Broadcast\UpdateBroadcastRequest;
use App\Http\Resources\Admin\Broadcast\BroadcastResource;
use App\Models\Broadcast;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BroadcastController extends Controller
{
    public function index(): JsonResponse
    {
        return (new ListBroadcastsAction)->handle();
    }

    /** مركز عمليات البثّ — تجميع تشغيليّ للوحة القيادة (B9). */
    public function dashboard(): JsonResponse
    {
        return ApiResponse::success(data: (new BroadcastDashboardAction)->handle());
    }

    /** تحليلات بثّ واحد (سياقيّة) — نطاق زمني عبر range/from/to. */
    public function analytics(Request $request, Broadcast $broadcast): JsonResponse
    {
        return (new BroadcastEntityAnalyticsAction)->handle(
            $broadcast,
            $request->query('range'),
            $request->query('from'),
            $request->query('to'),
        );
    }

    public function show(Broadcast $broadcast): JsonResponse
    {
        return ApiResponse::success(
            data: new BroadcastResource($broadcast->load(['category', 'creator', 'cover']))
        );
    }

    public function store(StoreBroadcastRequest $request): JsonResponse
    {
        return (new CreateBroadcastAction)->handle($request->validated(), $request->user());
    }

    public function update(UpdateBroadcastRequest $request, Broadcast $broadcast): JsonResponse
    {
        return (new UpdateBroadcastAction)->handle($broadcast, $request->validated(), $request->user());
    }

    public function destroy(Broadcast $broadcast): JsonResponse
    {
        return (new DeleteBroadcastAction)->handle($broadcast);
    }
}
