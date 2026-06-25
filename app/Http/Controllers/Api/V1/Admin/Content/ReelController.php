<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Content;

use App\Actions\Admin\Content\CreateReelAction;
use App\Actions\Admin\Content\DeleteReelAction;
use App\Actions\Admin\Content\ForceDeleteReelAction;
use App\Actions\Admin\Content\ListReelsAction;
use App\Actions\Admin\Content\ReelAnalyticsAction;
use App\Actions\Admin\Content\ReelEntityAnalyticsAction;
use App\Actions\Admin\Content\ReelStatsAction;
use App\Actions\Admin\Content\RestoreReelAction;
use App\Actions\Admin\Content\TransitionReelStatusAction;
use App\Actions\Admin\Content\UpdateReelAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Content\StoreReelRequest;
use App\Http\Requests\Admin\Content\TransitionReelRequest;
use App\Http\Requests\Admin\Content\UpdateReelRequest;
use App\Http\Resources\Admin\Content\ReelResource;
use App\Models\Reel;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReelController extends Controller
{
    public function index(): JsonResponse
    {
        return (new ListReelsAction)->handle();
    }

    /** عدّادات بطاقات الحالة للوحة الريلز. */
    public function stats(): JsonResponse
    {
        return (new ReelStatsAction)->handle();
    }

    /** تحليلات أسطول الريلز (مجاميع + متصدّرون + وقت نشر + لغة + أثر تمييز). */
    public function analytics(): JsonResponse
    {
        return (new ReelAnalyticsAction)->handle();
    }

    /** تحليلات ريل واحد (سياقيّة) — نطاق زمني عبر range/from/to. */
    public function entityAnalytics(Request $request, Reel $reel): JsonResponse
    {
        return (new ReelEntityAnalyticsAction)->handle(
            $reel,
            $request->query('range'),
            $request->query('from'),
            $request->query('to'),
        );
    }

    public function show(Reel $reel): JsonResponse
    {
        return ApiResponse::success(
            data: new ReelResource(
                $reel->load(['author:id,name', 'mediaAsset', 'engagementCounter'])
            )
        );
    }

    public function store(StoreReelRequest $request): JsonResponse
    {
        return (new CreateReelAction)->handle($request->validated(), $request->user());
    }

    public function update(UpdateReelRequest $request, Reel $reel): JsonResponse
    {
        return (new UpdateReelAction)->handle($reel, $request->validated(), $request->user());
    }

    public function status(TransitionReelRequest $request, Reel $reel): JsonResponse
    {
        return (new TransitionReelStatusAction)->handle($reel, $request->validated(), $request->user());
    }

    public function destroy(Reel $reel): JsonResponse
    {
        return (new DeleteReelAction)->handle($reel);
    }

    public function restore(Reel $reel): JsonResponse
    {
        return (new RestoreReelAction)->handle($reel);
    }

    public function forceDelete(Reel $reel): JsonResponse
    {
        return (new ForceDeleteReelAction)->handle($reel);
    }
}
