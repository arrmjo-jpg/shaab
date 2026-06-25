<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Advertising;

use App\Actions\Admin\Advertising\CreateAdZoneAction;
use App\Actions\Admin\Advertising\DeleteAdZoneAction;
use App\Actions\Admin\Advertising\ListAdZonesAction;
use App\Actions\Admin\Advertising\UpdateAdZoneAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Advertising\StoreAdZoneRequest;
use App\Http\Requests\Admin\Advertising\UpdateAdZoneRequest;
use App\Http\Resources\Admin\Advertising\AdZoneResource;
use App\Models\AdZone;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * إدارة المساحات الإعلانية — تحكّم رفيع (يستدعي الـ Actions فقط). التفويض عبر
 * permission middleware (ad-zones.view / ad-zones.manage) على المسارات.
 */
class AdZoneController extends Controller
{
    public function index(): JsonResponse
    {
        return (new ListAdZonesAction)->handle();
    }

    public function show(AdZone $adZone): JsonResponse
    {
        return ApiResponse::success(data: new AdZoneResource($adZone->loadCount('placements')));
    }

    public function store(StoreAdZoneRequest $request): JsonResponse
    {
        return (new CreateAdZoneAction)->handle($request->validated());
    }

    public function update(UpdateAdZoneRequest $request, AdZone $adZone): JsonResponse
    {
        return (new UpdateAdZoneAction)->handle($adZone, $request->validated());
    }

    public function destroy(AdZone $adZone): JsonResponse
    {
        return (new DeleteAdZoneAction)->handle($adZone);
    }
}
