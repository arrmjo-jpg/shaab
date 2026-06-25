<?php

declare(strict_types=1);

namespace App\Actions\Admin\Roles;

use App\Models\Role;
use App\Models\User;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class DeleteRoleAction
{
    public function handle(Role $role, User $actor): JsonResponse
    {
        // دور مدير النظام محمي من الحذف
        if ($role->name === 'super_admin') {
            return ApiResponse::error(__('role.cannot_delete_super_admin'), [], 403);
        }

        // منع قفل الذات: لا تحذف دوراً تحمله أنت
        if ($actor->hasRole($role->name)) {
            return ApiResponse::error(__('role.cannot_delete_own_role'), [], 403);
        }

        $role->delete();

        Cache::tags(['rbac'])->flush();

        return ApiResponse::success(__('role.deleted'));
    }
}
