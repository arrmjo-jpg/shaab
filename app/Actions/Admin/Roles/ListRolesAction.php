<?php

declare(strict_types=1);

namespace App\Actions\Admin\Roles;

use App\Http\Resources\Admin\Roles\RoleResource;
use App\Models\Role;
use App\Support\Cache\CacheKeys;
use App\Support\Cache\CacheTtl;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ListRolesAction
{
    public function handle(): JsonResponse
    {
        $default = (int) config('performance.pagination.default');
        $max = (int) config('performance.pagination.max');
        $perPage = max(1, min((int) request()->integer('per_page', $default), $max));

        $hasFilters = request()->hasAny(['filter', 'sort']);
        $isDefaultView = ! $hasFilters
            && (int) request()->integer('page', 1) === 1
            && $perPage === $default;

        $build = fn (): array => $this->query($perPage);

        // كاش متعمّد للعرض الافتراضي فقط (المسار الساخن) — تجنّب انفجار المفاتيح
        $payload = $isDefaultView
            ? Cache::tags(['rbac'])->remember(CacheKeys::rolesList(), CacheTtl::METADATA, $build)
            : $build();

        return ApiResponse::success(data: $payload['data'], meta: $payload['meta']);
    }

    private function query(int $perPage): array
    {
        $roles = QueryBuilder::for(Role::class)
            ->with('permissions:id,name,display_name,group')
            ->withCount(['permissions', 'users'])
            ->allowedFilters(
                AllowedFilter::partial('name'),
                AllowedFilter::partial('display_name'),
                AllowedFilter::callback('search', function ($query, $value): void {
                    $query->where(function ($q) use ($value): void {
                        $q->where('name', 'like', "%{$value}%")
                            ->orWhere('display_name', 'like', "%{$value}%");
                    });
                }),
            )
            ->allowedSorts('id', 'name', 'created_at')
            ->defaultSort('name')
            ->paginate($perPage)
            ->appends(request()->query());

        return [
            'data' => RoleResource::collection($roles)->resolve(),
            'meta' => [
                'pagination' => [
                    'total' => $roles->total(),
                    'count' => $roles->count(),
                    'per_page' => $roles->perPage(),
                    'current_page' => $roles->currentPage(),
                    'total_pages' => $roles->lastPage(),
                ],
            ],
        ];
    }
}
