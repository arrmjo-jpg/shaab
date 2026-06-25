<?php

declare(strict_types=1);

namespace App\Actions\Admin\Advertising;

use App\Http\Resources\Admin\Advertising\AdCreativeResource;
use App\Models\AdCreative;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * قائمة إبداعات الإدارة — ترشيح (حملة/نوع/نشاط) + فرز/ترقيم + رؤية المحذوف. تشمل اسم
 * الحملة وعدّاد الإسنادات. تتبع اتفاقية الترقيم الموحّدة (meta.pagination).
 */
class ListAdCreativesAction
{
    public function handle(): JsonResponse
    {
        $default = (int) config('performance.pagination.default');
        $max = (int) config('performance.pagination.max');
        $perPage = max(1, min((int) request()->integer('per_page', $default), $max));

        $query = QueryBuilder::for(AdCreative::class)
            ->with(['campaign:id,name,status', 'mediaAsset'])
            ->withCount('placements')
            ->allowedFilters(
                AllowedFilter::exact('ad_campaign_id'),
                AllowedFilter::exact('type'),
                AllowedFilter::exact('is_active'),
                AllowedFilter::partial('title'),
            )
            ->allowedSorts('id', 'title', 'weight', 'created_at')
            ->defaultSort('-created_at');

        $trashed = (string) request()->query('trashed', '');
        if ($trashed === 'only') {
            $query->onlyTrashed();
        } elseif ($trashed === 'with') {
            $query->withTrashed();
        }

        $creatives = $query->paginate($perPage)->appends(request()->query());

        return ApiResponse::success(
            data: AdCreativeResource::collection($creatives)->resolve(),
            meta: [
                'pagination' => [
                    'total' => $creatives->total(),
                    'count' => $creatives->count(),
                    'per_page' => $creatives->perPage(),
                    'current_page' => $creatives->currentPage(),
                    'total_pages' => $creatives->lastPage(),
                ],
            ]
        );
    }
}
