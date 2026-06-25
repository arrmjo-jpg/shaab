<?php

declare(strict_types=1);

namespace App\Actions\Admin\Ad;

use App\Http\Resources\Admin\Ad\AdRequestResource;
use App\Models\AdRequest;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * تغيير حالة طلب إعلان + تسجيل المُراجِع/وقته (تتبّع مبيعات). تدقيق تلقائيّ (status/reviewed_by/at).
 */
class UpdateAdRequestStatusAction
{
    public function handle(AdRequest $adRequest, string $status, int $userId): JsonResponse
    {
        $adRequest->status = $status;
        $adRequest->reviewed_by = $userId;
        $adRequest->reviewed_at = now();
        $adRequest->save();

        return ApiResponse::success(
            message: __('ad_request.status_changed'),
            data: new AdRequestResource($adRequest->loadMissing('reviewedBy')),
        );
    }
}
