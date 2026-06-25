<?php

declare(strict_types=1);

namespace App\Actions\Admin\Auth;

use App\Enums\UserStatus;
use App\Http\Resources\Admin\Auth\AdminAuthResource;
use App\Models\User;
use App\Support\Auth\AuthActivity;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class AdminLoginAction
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

    public function handle(array $validated, string $ip): JsonResponse
    {
        $user = User::where('email', $validated['email'])->first();

        // نتيجة تجارية متوقعة — لا نفرّق بين مستخدم غير موجود وكلمة مرور خاطئة
        if ($user === null || ! Hash::check($validated['password'], $user->password)) {
            return ApiResponse::error(__('auth.failed'), [], 401);
        }

        if ($user->status === UserStatus::Suspended) {
            return ApiResponse::error(__('auth.suspended'), [], 403);
        }

        if ($user->status === UserStatus::Banned) {
            return ApiResponse::error(__('auth.banned'), [], 403);
        }

        // التحقق من الدور الإداري قبل إصدار أي token — fail early
        // نفس استجابة بيانات الاعتماد الخاطئة لعدم الكشف عن وجود الحساب
        if (! $user->hasAnyRole(self::ADMIN_ROLES)) {
            return ApiResponse::error(__('auth.failed'), [], 401);
        }

        // البريد يجب أن يكون مؤكَّداً — يُحجَب الدخول ويُحوَّل المستخدم
        // لصفحة التأكيد (يُفحَص بعد الدور حتى لا يُكشَف لغير الإداريين).
        if ($user->email_verified_at === null) {
            return ApiResponse::error(
                __('auth.email_unverified'),
                ['code' => 'email_unverified'],
                403
            );
        }

        $user->recordLogin($ip);
        AuthActivity::log('admin_login', $user);

        $token = $user->createToken('admin-token', ['admin'])->plainTextToken;

        return ApiResponse::success(
            __('auth.admin_login_success'),
            new AdminAuthResource($user, $token)
        );
    }
}
