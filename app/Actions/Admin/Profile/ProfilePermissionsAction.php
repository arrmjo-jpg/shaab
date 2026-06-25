<?php

declare(strict_types=1);

namespace App\Actions\Admin\Profile;

use App\Models\User;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * رؤية صلاحيات المستخدم بشكل مقروء — الأدوار + الصلاحيات الفعلية مجمّعة حسب
 * مجموعة الصلاحيات (PermissionGroup)، لا تفريغ خام. تعكس القدرات الفعلية (effective)
 * المشتقّة من كل أدواره. مدير النظام يُعلَّم بوصول كامل.
 */
class ProfilePermissionsAction
{
    public function handle(User $user): JsonResponse
    {
        $permissions = $user->getAllPermissions()->load('permissionGroup');

        $groups = $permissions
            ->groupBy(fn ($p) => $p->permissionGroup?->display_name ?? __('profile.permissions.ungrouped'))
            ->map(fn ($items, $label): array => [
                'group' => (string) $label,
                'count' => $items->count(),
                'permissions' => $items
                    ->sortBy('name')
                    ->map(fn ($p): array => [
                        'name' => $p->name,
                        'display_name' => $p->display_name ?? $p->name,
                    ])->values(),
            ])
            ->sortBy('group')
            ->values();

        return ApiResponse::success(data: [
            'roles' => $user->roles->map(fn ($r): array => [
                'name' => $r->name,
                'display_name' => $r->display_name,
            ])->values(),
            'is_super_admin' => $user->hasRole('super_admin'),
            'summary' => [
                'roles_count' => $user->roles->count(),
                'permissions_count' => $permissions->count(),
                'groups_count' => $groups->count(),
            ],
            'groups' => $groups,
        ]);
    }
}
