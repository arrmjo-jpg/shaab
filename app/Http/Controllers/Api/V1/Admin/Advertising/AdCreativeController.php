<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Advertising;

use App\Actions\Admin\Advertising\CreateAdCreativeAction;
use App\Actions\Admin\Advertising\DeleteAdCreativeAction;
use App\Actions\Admin\Advertising\ForceDeleteAdCreativeAction;
use App\Actions\Admin\Advertising\ListAdCreativesAction;
use App\Actions\Admin\Advertising\RestoreAdCreativeAction;
use App\Actions\Admin\Advertising\UpdateAdCreativeAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Advertising\StoreAdCreativeRequest;
use App\Http\Requests\Admin\Advertising\UpdateAdCreativeRequest;
use App\Http\Resources\Admin\Advertising\AdCreativeResource;
use App\Models\AdCreative;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * إدارة الإبداعات الإعلانية — تحكّم رفيع (يستدعي الـ Actions فقط). لا مسار حالة (التفعيل
 * عبر تحديث is_active). تنقية HTML تُطبَّق في الـ Action. التفويض عبر permission middleware.
 */
class AdCreativeController extends Controller
{
    public function index(): JsonResponse
    {
        return (new ListAdCreativesAction)->handle();
    }

    public function show(AdCreative $adCreative): JsonResponse
    {
        return ApiResponse::success(
            data: new AdCreativeResource($adCreative->load(['campaign:id,name,status', 'mediaAsset'])->loadCount('placements'))
        );
    }

    public function store(StoreAdCreativeRequest $request): JsonResponse
    {
        return (new CreateAdCreativeAction)->handle($request->validated());
    }

    public function update(UpdateAdCreativeRequest $request, AdCreative $adCreative): JsonResponse
    {
        return (new UpdateAdCreativeAction)->handle($adCreative, $request->validated());
    }

    public function destroy(AdCreative $adCreative): JsonResponse
    {
        return (new DeleteAdCreativeAction)->handle($adCreative);
    }

    public function restore(AdCreative $adCreative): JsonResponse
    {
        return (new RestoreAdCreativeAction)->handle($adCreative);
    }

    public function forceDelete(AdCreative $adCreative): JsonResponse
    {
        return (new ForceDeleteAdCreativeAction)->handle($adCreative);
    }
}
