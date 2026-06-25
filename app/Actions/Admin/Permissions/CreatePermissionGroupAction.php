<?php

declare(strict_types=1);

namespace App\Actions\Admin\Permissions;

use App\Http\Resources\Admin\Permissions\PermissionGroupResource;
use App\Models\PermissionGroup;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class CreatePermissionGroupAction
{
    public function handle(array $validated): JsonResponse
    {
        $group = PermissionGroup::create([
            'slug' => $validated['slug'],
            'display_name' => $validated['display_name'],
            'description' => $validated['description'] ?? null,
            'icon' => $validated['icon'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_system' => false,
        ]);

        Cache::tags(['rbac'])->flush();

        return ApiResponse::success(
            __('permission_group.created'),
            new PermissionGroupResource($group->loadCount('permissions')),
            201
        );
    }
}
