<?php

declare(strict_types=1);

namespace App\Actions\Admin\Permissions;

use App\Http\Resources\Admin\Permissions\PermissionGroupResource;
use App\Models\PermissionGroup;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class UpdatePermissionGroupAction
{
    public function handle(PermissionGroup $group, array $validated): JsonResponse
    {
        // المجموعات النظامية محميّة: لا يجوز تغيير الـ slug
        if ($group->is_system && array_key_exists('slug', $validated) && $validated['slug'] !== $group->slug) {
            return ApiResponse::error(__('permission_group.system_slug_protected'), [], 403);
        }

        foreach (['slug', 'display_name', 'description', 'icon', 'sort_order'] as $field) {
            if (array_key_exists($field, $validated)) {
                $group->{$field} = $validated[$field];
            }
        }
        $group->save();

        Cache::tags(['rbac'])->flush();

        return ApiResponse::success(
            __('permission_group.updated'),
            new PermissionGroupResource($group->loadCount('permissions'))
        );
    }
}
