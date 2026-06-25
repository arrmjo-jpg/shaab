<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Advertising;

use App\Actions\Admin\Advertising\ChangeAdCampaignStatusAction;
use App\Actions\Admin\Advertising\CreateAdCampaignAction;
use App\Actions\Admin\Advertising\DeleteAdCampaignAction;
use App\Actions\Admin\Advertising\ForceDeleteAdCampaignAction;
use App\Actions\Admin\Advertising\ListAdCampaignsAction;
use App\Actions\Admin\Advertising\RestoreAdCampaignAction;
use App\Actions\Admin\Advertising\UpdateAdCampaignAction;
use App\Enums\AdCampaignStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Advertising\ChangeAdCampaignStatusRequest;
use App\Http\Requests\Admin\Advertising\StoreAdCampaignRequest;
use App\Http\Requests\Admin\Advertising\UpdateAdCampaignRequest;
use App\Http\Resources\Admin\Advertising\AdCampaignResource;
use App\Models\AdCampaign;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * إدارة الحملات الإعلانية — تحكّم رفيع (يستدعي الـ Actions فقط). انتقالات الحالة عبر مسار
 * status مستقل (آلة الحالة تفرض الشرعية). التفويض عبر permission middleware على المسارات.
 */
class AdCampaignController extends Controller
{
    public function index(): JsonResponse
    {
        return (new ListAdCampaignsAction)->handle();
    }

    public function show(AdCampaign $adCampaign): JsonResponse
    {
        return ApiResponse::success(data: new AdCampaignResource($adCampaign->loadCount('creatives')));
    }

    public function store(StoreAdCampaignRequest $request): JsonResponse
    {
        return (new CreateAdCampaignAction)->handle($request->validated(), $request->user());
    }

    public function update(UpdateAdCampaignRequest $request, AdCampaign $adCampaign): JsonResponse
    {
        return (new UpdateAdCampaignAction)->handle($adCampaign, $request->validated(), $request->user());
    }

    public function status(ChangeAdCampaignStatusRequest $request, AdCampaign $adCampaign): JsonResponse
    {
        $to = AdCampaignStatus::from($request->validated()['status']);

        return (new ChangeAdCampaignStatusAction)->handle($adCampaign, $to, $request->user());
    }

    public function destroy(AdCampaign $adCampaign): JsonResponse
    {
        return (new DeleteAdCampaignAction)->handle($adCampaign);
    }

    public function restore(AdCampaign $adCampaign): JsonResponse
    {
        return (new RestoreAdCampaignAction)->handle($adCampaign);
    }

    public function forceDelete(AdCampaign $adCampaign): JsonResponse
    {
        return (new ForceDeleteAdCampaignAction)->handle($adCampaign);
    }
}
