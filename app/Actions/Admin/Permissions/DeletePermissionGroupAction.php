<?php

declare(strict_types=1);

namespace App\Actions\Admin\Permissions;

use App\Models\PermissionGroup;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class DeletePermissionGroupAction
{
    public function handle(PermissionGroup $group): JsonResponse
    {
        // المجموعات النظامية محميّة من الحذف
        if ($group->is_system) {
            return ApiResponse::error(__('permission_group.cannot_delete_system'), [], 403);
        }

        // فك ارتباط الصلاحيات (تبقى الصلاحيات، يُلغى انتماؤها فقط)
        $group->delete();

        Cache::tags(['rbac'])->flush();

        return ApiResponse::success(__('permission_group.deleted'));
    }
}
