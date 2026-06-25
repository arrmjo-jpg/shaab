<?php

declare(strict_types=1);

namespace App\Actions\Admin\Advertising;

use App\Http\Resources\Admin\Advertising\AdZoneResource;
use App\Models\AdZone;
use App\Support\Advertising\AdServingInvalidator;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class UpdateAdZoneAction
{
    /** @param  array<string, mixed>  $data */
    public function handle(AdZone $zone, array $data): JsonResponse
    {
        $oldKey = $zone->key;
        $zone->update($data);

        // إبطال بِركة المساحة (والمفتاح القديم عند تغيّر الـ key — يمنع بقايا قديمة).
        $keys = [$zone->key];
        if ($oldKey !== $zone->key) {
            $keys[] = $oldKey;
        }
        AdServingInvalidator::flushZones($keys);

        return ApiResponse::success(__('ads.zone.updated'), new AdZoneResource($zone->fresh()));
    }
}
