<?php

declare(strict_types=1);

namespace App\Actions\Admin\Permissions;

use App\Http\Resources\Admin\Permissions\PermissionGroupResource;
use App\Models\PermissionGroup;
use App\Support\Cache\CacheKeys;
use App\Support\Cache\CacheTtl;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class ListPermissionGroupsAction
{
    public function handle(): JsonResponse
    {
        $payload = Cache::tags(['rbac'])->remember(
            CacheKeys::permissionGroups(),
            CacheTtl::METADATA,
            fn (): array => PermissionGroupResource::collection(
                PermissionGroup::query()
                    ->with('permissions:id,name,display_name,description,permission_group_id')
                    ->withCount('permissions')
                    ->orderBy('sort_order')
                    ->get()
            )->resolve()
        );

        return ApiResponse::success(data: $payload);
    }
}
