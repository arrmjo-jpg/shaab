<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Advertising;

use App\Actions\Admin\Advertising\AttachAdPlacementAction;
use App\Actions\Admin\Advertising\DetachAdPlacementAction;
use App\Actions\Admin\Advertising\ListAdPlacementsAction;
use App\Actions\Admin\Advertising\UpdateAdPlacementAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Advertising\StoreAdPlacementRequest;
use App\Http\Requests\Admin\Advertising\UpdateAdPlacementRequest;
use App\Http\Resources\Admin\Advertising\AdPlacementResource;
use App\Models\AdPlacement;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * إدارة إسنادات الإعلانات (إبداع ↔ مساحة) — تحكّم رفيع (يستدعي الـ Actions فقط). القيود
 * الإعداديّة (التوافق/التكرار) تُفرَض في AttachAdPlacementAction. لا حذف ناعم/استرجاع
 * (الإسناد رابط). التفويض عبر permission middleware على المسارات.
 */
class AdPlacementController extends Controller
{
    public function index(): JsonResponse
    {
        return (new ListAdPlacementsAction)->handle();
    }

    public function show(AdPlacement $adPlacement): JsonResponse
    {
        return ApiResponse::success(
            data: new AdPlacementResource(
                $adPlacement->load(['creative:id,title,type,weight', 'zone:id,key,name,placement_type'])
            )
        );
    }

    public function store(StoreAdPlacementRequest $request): JsonResponse
    {
        return (new AttachAdPlacementAction)->handle($request->validated());
    }

    public function update(UpdateAdPlacementRequest $request, AdPlacement $adPlacement): JsonResponse
    {
        return (new UpdateAdPlacementAction)->handle($adPlacement, $request->validated());
    }

    public function destroy(AdPlacement $adPlacement): JsonResponse
    {
        return (new DetachAdPlacementAction)->handle($adPlacement);
    }
}
