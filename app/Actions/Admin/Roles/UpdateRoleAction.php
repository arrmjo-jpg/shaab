<?php

declare(strict_types=1);

namespace App\Actions\Admin\Roles;

use App\Http\Resources\Admin\Roles\RoleResource;
use App\Models\Role;
use App\Models\User;
use App\Support\Audit\RbacAudit;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class UpdateRoleAction
{
    // الصلاحيات الحرجة التي لا يجوز للمدير سحبها من دوره
    private const CRITICAL_ACCESS = ['roles.view', 'roles.edit'];

    public function handle(Role $role, User $actor, array $validated): JsonResponse
    {
        $isSuperAdmin = $role->name === 'super_admin';

        // دور مدير النظام محمي: لا تغيير للاسم ولا للصلاحيات
        if ($isSuperAdmin && (array_key_exists('name', $validated) || array_key_exists('permissions', $validated))) {
            return ApiResponse::error(__('role.super_admin_protected'), [], 403);
        }

        // منع قفل الذات: لا تسحب صلاحياتك الحرجة من دور تحمله أنت
        if (
            array_key_exists('permissions', $validated)
            && $actor->hasRole($role->name)
        ) {
            $next = $validated['permissions'] ?? [];
            foreach (self::CRITICAL_ACCESS as $critical) {
                if (! in_array($critical, $next, true)) {
                    return ApiResponse::error(__('role.cannot_remove_own_critical_access'), [], 403);
                }
            }
        }

        $permissionsChanged = array_key_exists('permissions', $validated);
        $oldPermissions = $permissionsChanged ? $role->permissions->pluck('name')->all() : [];
        $newPermissions = array_values($validated['permissions'] ?? []);

        DB::transaction(function () use ($role, $validated, $permissionsChanged, $newPermissions): void {
            if (array_key_exists('name', $validated)) {
                $role->name = $validated['name'];
            }
            if (array_key_exists('display_name', $validated)) {
                $role->display_name = $validated['display_name'];
            }
            if (array_key_exists('description', $validated)) {
                $role->description = $validated['description'];
            }
            $role->save();

            if ($permissionsChanged) {
                $role->syncPermissions($newPermissions);
            }
        });

        // تدقيق صريح لتغيّر صلاحيات الدور (منح/سحب) — لا تلتقطه أحداث النموذج.
        if ($permissionsChanged) {
            RbacAudit::rolePermissions($actor, $role, $oldPermissions, $newPermissions);
        }

        Cache::tags(['rbac'])->flush();

        return ApiResponse::success(
            __('role.updated'),
            new RoleResource($role->load('permissions')->loadCount(['permissions', 'users']))
        );
    }
}
