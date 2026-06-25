<?php

declare(strict_types=1);

namespace App\Actions\Admin\Advertising;

use App\Http\Resources\Admin\Advertising\AdZoneResource;
use App\Models\AdZone;
use App\Support\Advertising\AdServingInvalidator;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class CreateAdZoneAction
{
    /** @param  array<string, mixed>  $data */
    public function handle(array $data): JsonResponse
    {
        $zone = AdZone::create($data);

        // إبطال أي بِركة سالبة-التخزين سبق طلبها تحت هذا المفتاح (دفاعيّ).
        AdServingInvalidator::flushZones([$zone->key]);

        // fresh(): يضمن تحميل قيم الأعمدة الافتراضية (selector_strategy/sort_order…).
        return ApiResponse::success(__('ads.zone.created'), new AdZoneResource($zone->fresh()), 201);
    }
}
