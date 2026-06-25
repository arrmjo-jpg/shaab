<?php

declare(strict_types=1);

namespace App\Support\Audit;

use App\Models\Role;
use App\Models\User;

/**
 * تدقيق صريح لتغييرات الامتيازات (الأدوار/الصلاحيات). أحداث Eloquent لا تلتقط هذه
 * التغييرات لأنها تقع على جداول الربط (model_has_roles / role_has_permissions)،
 * لا على سمات النموذج — لذا نسجّلها يدوياً. يُسجَّل: الفاعل (causer)، الهدف
 * (subject)، القيم القديمة/الجديدة (أسماء فقط — ليست أسراراً)، والوقت.
 *
 * مساعد ساكن بلا حالة — يُستدعى مباشرةً من الإجراءات (Actions).
 */
final class RbacAudit
{
    /**
     * تغيّر أدوار مستخدم (إسناد/سحب). لا يُسجَّل شيء إن لم تتغيّر المجموعة فعلياً.
     *
     * @param  array<int,string>  $old
     * @param  array<int,string>  $new
     */
    public static function userRoles(?User $actor, User $target, array $old, array $new): void
    {
        $old = self::normalize($old);
        $new = self::normalize($new);

        if ($old === $new) {
            return;
        }

        activity('rbac')
            ->causedBy($actor)
            ->performedOn($target)
            ->event('user_roles_updated')
            ->withProperties([
                'old' => $old,
                'new' => $new,
                'added' => array_values(array_diff($new, $old)),
                'removed' => array_values(array_diff($old, $new)),
                'timestamp' => now()->toISOString(),
            ])
            ->log(__('audit.rbac.user_roles_updated'));
    }

    /**
     * تغيّر صلاحيات دور (منح/سحب). لا يُسجَّل شيء إن لم تتغيّر المجموعة فعلياً.
     *
     * @param  array<int,string>  $old
     * @param  array<int,string>  $new
     */
    public static function rolePermissions(?User $actor, Role $role, array $old, array $new): void
    {
        $old = self::normalize($old);
        $new = self::normalize($new);

        if ($old === $new) {
            return;
        }

        activity('rbac')
            ->causedBy($actor)
            ->performedOn($role)
            ->event('role_permissions_updated')
            ->withProperties([
                'role' => $role->name,
                'old' => $old,
                'new' => $new,
                'added' => array_values(array_diff($new, $old)),
                'removed' => array_values(array_diff($old, $new)),
                'timestamp' => now()->toISOString(),
            ])
            ->log(__('audit.rbac.role_permissions_updated'));
    }

    /**
     * @param  array<int,string>  $values
     * @return array<int,string>
     */
    private static function normalize(array $values): array
    {
        $values = array_values(array_unique(array_map(static fn ($v): string => (string) $v, $values)));
        sort($values);

        return $values;
    }
}
