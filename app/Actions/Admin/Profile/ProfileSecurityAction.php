<?php

declare(strict_types=1);

namespace App\Actions\Admin\Profile;

use App\Models\User;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * مركز الأمان — ملخّص حالة أمان الحساب من مصادر حقيقية: تأكيد البريد، آخر دخول،
 * آخر تغيير لكلمة المرور وعدد طلبات إعادة التعيين (مشتقّة من سجل النشاط auth)،
 * وعدد الجلسات النشطة (توكنات Sanctum). لا تخزين جديد — يعيد استخدام سجل التدقيق.
 */
class ProfileSecurityAction
{
    public function handle(User $user): JsonResponse
    {
        $morph = $user->getMorphClass();
        $id = $user->getKey();

        $lastPasswordChange = Activity::query()
            ->where('log_name', 'auth')
            ->where('event', 'password_changed')
            ->where('causer_type', $morph)
            ->where('causer_id', $id)
            ->latest('id')
            ->value('created_at');

        $resetQuery = Activity::query()
            ->where('log_name', 'auth')
            ->where('event', 'password_reset_requested')
            ->where('subject_type', $morph)
            ->where('subject_id', $id);

        $resetCount = (clone $resetQuery)->count();
        $lastReset = (clone $resetQuery)->latest('id')->value('created_at');

        return ApiResponse::success(data: [
            'email_verified' => $user->email_verified_at !== null,
            'email_verified_at' => $user->email_verified_at?->toISOString(),
            'last_login_at' => $user->last_login_at?->toISOString(),
            'last_login_ip' => $user->last_login_ip,
            'password_changed_at' => $lastPasswordChange
                ? Carbon::parse($lastPasswordChange)->toISOString()
                : null,
            'reset_requests_count' => $resetCount,
            'last_reset_requested_at' => $lastReset
                ? Carbon::parse($lastReset)->toISOString()
                : null,
            'active_sessions_count' => $user->tokens()->count(),
            'account_created_at' => $user->created_at->toISOString(),
        ]);
    }
}
