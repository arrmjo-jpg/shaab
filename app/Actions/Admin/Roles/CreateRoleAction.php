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

class CreateRoleAction
{
    public function handle(array $validated, ?User $actor = null): JsonResponse
    {
        $role = DB::transaction(function () use ($validated): Role {
            $role = Role::create([
                'name' => $validated['name'],
                'guard_name' => 'web',
                'display_name' => $validated['display_name'],
                'description' => $validated['description'] ?? null,
            ]);

            if (! empty($validated['permissions'])) {
                $role->syncPermissions($validated['permissions']);
            }

            return $role;
        });

        // تدقيق صريح للصلاحيات المُسندة عند إنشاء الدور (إن وُجدت).
        if (! empty($validated['permissions'])) {
            RbacAudit::rolePermissions($actor, $role, [], array_values($validated['permissions']));
        }

        // إبطال موحّد بالوسم — يغطّي الأدوار والصلاحيات ومجموعاتها (بعد الالتزام)
        Cache::tags(['rbac'])->flush();

        return ApiResponse::success(
            __('role.created'),
            new RoleResource($role->load('permissions')->loadCount(['permissions', 'users'])),
            201
        );
    }
}
