<?php

declare(strict_types=1);

namespace App\Actions\Admin\Ad;

use App\Models\AdRequest;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * حذف ناعم لطلب إعلان (SoftDeletes) — لا حذف نهائيّ تلقائيّ.
 */
class DeleteAdRequestAction
{
    public function handle(AdRequest $adRequest): JsonResponse
    {
        $adRequest->delete();

        return ApiResponse::success(message: __('ad_request.deleted'));
    }
}
