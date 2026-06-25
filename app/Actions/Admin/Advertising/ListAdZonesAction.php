<?php

declare(strict_types=1);

namespace App\Actions\Admin\Advertising;

use App\Http\Resources\Admin\Advertising\AdZoneResource;
use App\Models\AdZone;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * قائمة المساحات الإعلانية — كيان إعداد منخفض العدد (لا ترقيم صفحات؛ مرآة التصنيفات).
 * تشمل عدّاد الإسنادات لإرشاد الإدارة قبل الحذف.
 */
class ListAdZonesAction
{
    public function handle(): JsonResponse
    {
        $zones = AdZone::query()
            ->withCount('placements')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return ApiResponse::success(data: AdZoneResource::collection($zones));
    }
}
