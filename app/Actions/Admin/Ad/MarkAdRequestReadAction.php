<?php

declare(strict_types=1);

namespace App\Actions\Admin\Ad;

use App\Http\Resources\Admin\Ad\AdRequestResource;
use App\Models\AdRequest;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Mark as Read — read_at مرّة واحدة (seen بصريّ). لا يغيّر status ولا يقود Badge.
 */
class MarkAdRequestReadAction
{
    public function handle(AdRequest $adRequest): JsonResponse
    {
        if ($adRequest->read_at === null) {
            $adRequest->read_at = now();
            $adRequest->save();
        }

        return ApiResponse::success(
            data: new AdRequestResource($adRequest->loadMissing('reviewedBy')),
        );
    }
}
