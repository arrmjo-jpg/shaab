<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public\Ad;

use App\Actions\Public\Ad\CreateAdRequestAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\Ad\StorePublicAdRequestRequest;
use Illuminate\Http\JsonResponse;

/**
 * استقبال «طلب إعلان» العامّ. الحماية على المسار (recaptcha:ad_request + throttle:public.ad-request).
 */
class AdRequestController extends Controller
{
    public function store(StorePublicAdRequestRequest $request): JsonResponse
    {
        return (new CreateAdRequestAction)->handle($request->validated(), $request);
    }
}
