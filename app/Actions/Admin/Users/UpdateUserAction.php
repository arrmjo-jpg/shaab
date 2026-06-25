<?php

declare(strict_types=1);

namespace App\Actions\Admin\Users;

use App\Http\Resources\Admin\Users\UserResource;
use App\Models\User;
use App\Support\Audit\RbacAudit;
use App\Support\Authorization\RoleEscalationGuard;
use App\Support\Frontend\FrontendRevalidate;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class UpdateUserAction
{
    private const ADMIN_ROLES = [
        'super_admin',
        'editor',
        'reviewer',
        'moderator',
        'social_media_manager',
        'journalist',
        'contributor',
    ];

    public function handle(User $target, User $actor, array $validated): JsonResponse
    {
        // منع قفل الذات: لا تسحب آخر دور إداري عن نفسك عبر مزامنة الأدوار
        if (
            $actor->id === $target->id
            && array_key_exists('roles', $validated)
        ) {
            $next = $validated['roles'] ?? [];
            if (count(array_intersect($next, self::ADMIN_ROLES)) === 0) {
                return ApiResponse::error(__('user.cannot_remove_own_admin_roles'), [], 403);
            }
        }

        // قفل تصعيد الصلاحيات: حماية حساب super_admin ومنح دوره
        if ($denied = RoleEscalationGuard::check(
            $actor,
            $target,
            $validated['roles'] ?? [],
            array_key_exists('roles', $validated)
        )) {
            return $denied;
        }

        if (array_key_exists('name', $validated)) {
            $target->name = $validated['name'];
        }

        if (array_key_exists('email', $validated)) {
            $target->email = $validated['email'];
        }

        foreach (['avatar', 'bio', 'social_links'] as $field) {
            if (array_key_exists($field, $validated)) {
                $target->{$field} = $validated[$field];
            }
        }

        // تأكيد البريد يدوياً من المدير: true → الآن، false → غير مؤكَّد
        if (array_key_exists('email_verified', $validated)) {
            $verified = filter_var($validated['email_verified'], FILTER_VALIDATE_BOOLEAN);
            $target->email_verified_at = $verified ? now() : null;
        }

        // كاتب — مفتاح boolean مستقل عن الأدوار
        if (array_key_exists('is_writer', $validated)) {
            $target->is_writer = filter_var($validated['is_writer'], FILTER_VALIDATE_BOOLEAN);
        }

        // كلمة المرور اختيارية — تُحدَّث فقط عند تمريرها. عند تغيير المدير لها:
        // ندوّر remember_token ونُبطِل كل جلسات الهدف (الحساب قد يكون مخترَقاً).
        $passwordChanged = ! empty($validated['password']);
        if ($passwordChanged) {
            $target->password = $validated['password']; // cast hashed
            $target->remember_token = Str::random(60);
        }

        $target->save();

        // بروفايل الكاتب العامّ (/writer/{id}) يعرض الاسم/الصورة/علم is_writer — إبطال وسومه.
        FrontendRevalidate::tags(['writers', "writer:{$target->id}"]);

        if ($passwordChanged) {
            $target->tokens()->delete();
        }

        if (array_key_exists('roles', $validated)) {
            $oldRoles = $target->getRoleNames()->all();
            $newRoles = array_values($validated['roles'] ?? []);
            $target->syncRoles($newRoles);
            // تدقيق صريح لتغيّر أدوار المستخدم (تغيير الامتيازات لا تلتقطه أحداث
            // النموذج لأنه يقع على جدول الربط model_has_roles).
            RbacAudit::userRoles($actor, $target, $oldRoles, $newRoles);
        }

        return ApiResponse::success(
            __('user.updated'),
            new UserResource($target->load('roles'))
        );
    }
}
