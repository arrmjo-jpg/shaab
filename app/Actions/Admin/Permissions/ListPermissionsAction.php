<?php

declare(strict_types=1);

namespace App\Actions\Admin\Permissions;

use App\Http\Resources\Admin\Permissions\PermissionResource;
use App\Support\Cache\CacheKeys;
use App\Support\Cache\CacheTtl;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;

class ListPermissionsAction
{
    public function handle(): JsonResponse
    {
        $payload = Cache::tags(['rbac'])->remember(
            CacheKeys::permissionsGrouped(),
            CacheTtl::METADATA,
            fn (): array => Permission::query()
                ->orderBy('group')
                ->orderBy('name')
                ->get(['id', 'name', 'display_name', 'group', 'description', 'guard_name'])
                ->groupBy('group')
                ->map(fn ($items, $group): array => [
                    'group' => $group,
                    'items' => PermissionResource::collection($items)->resolve(),
                ])
                ->values()
                ->all()
        );

        return ApiResponse::success(data: $payload);
    }
}
