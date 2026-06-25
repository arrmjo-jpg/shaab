<?php

declare(strict_types=1);

namespace App\Actions\Admin\Profile;

use App\Models\User;
use App\Support\Auth\AuthActivity;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * تسجيل الخروج من كل الجلسات الأخرى — يُبطِل كل توكنات Sanctum عدا الجلسة الحالية.
 * إجراء أمني ذاتي (إنهاء جلسات أجهزة أخرى مشبوهة). يُدوَّن في سجل النشاط.
 */
class RevokeOtherSessionsAction
{
    public function handle(User $user, int|string|null $currentTokenId): JsonResponse
    {
        $revoked = $user->tokens()
            ->when($currentTokenId !== null, fn ($q) => $q->where('id', '!=', $currentTokenId))
            ->delete();

        AuthActivity::log('sessions_revoked_others', $user);

        return ApiResponse::success(
            __('profile.other_sessions_revoked'),
            ['revoked' => (int) $revoked]
        );
    }
}
