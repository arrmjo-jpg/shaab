<?php

declare(strict_types=1);

namespace App\Support\Authorization;

use App\Models\User;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * حارس تصعيد الصلاحيات — قفل صارم حول دور مدير النظام (super_admin).
 *
 * قاعدتان لا لبس فيهما (لا هرمية مفترَضة):
 *   A) منح دور super_admin لأي حساب لا يجوز إلا لفاعل يملك super_admin.
 *   B) أي تعديل على حساب يملك super_admin لا يجوز إلا لفاعل يملك super_admin
 *      (قفل صلب يشمل الأدوار وكلمة المرور وحالة الكاتب وتأكيد البريد).
 *
 * يُرجع JsonResponse عند الرفض، أو null إذا كان الإجراء مسموحاً.
 */
final class RoleEscalationGuard
{
    private const SUPER_ADMIN = 'super_admin';

    /**
     * @param  array<int, string>  $requestedRoles
     */
    public static function check(
        ?User $actor,
        ?User $target,
        array $requestedRoles,
        bool $rolesProvided
    ): ?JsonResponse {
        $actorIsSuper = $actor !== null && $actor->hasRole(self::SUPER_ADMIN);

        if ($actorIsSuper) {
            return null; // مدير النظام مخوّل بالكامل
        }

        // (B) قفل صلب: لا يُعدَّل حساب super_admin إلا بواسطة super_admin
        if ($target !== null && $target->hasRole(self::SUPER_ADMIN)) {
            return ApiResponse::error(__('user.cannot_modify_super_admin'), [], 403);
        }

        // (A) لا يُمنح دور super_admin إلا بواسطة super_admin
        if ($rolesProvided && in_array(self::SUPER_ADMIN, $requestedRoles, true)) {
            return ApiResponse::error(__('user.cannot_grant_super_admin'), [], 403);
        }

        return null;
    }
}
