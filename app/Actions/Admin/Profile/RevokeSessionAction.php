<?php

declare(strict_types=1);

namespace App\Actions\Admin\Profile;

use App\Models\User;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class RevokeSessionAction
{
    public function handle(User $user, int $tokenId, int|string|null $currentTokenId): JsonResponse
    {
        // لا يجوز إنهاء الجلسة الحالية يدوياً — استخدم تسجيل الخروج
        if ((int) $currentTokenId === $tokenId) {
            return ApiResponse::error(__('profile.cannot_revoke_current'), [], 422);
        }

        $token = $user->tokens()->whereKey($tokenId)->first();

        if ($token === null) {
            return ApiResponse::error(__('profile.session_not_found'), [], 404);
        }

        $token->delete();

        return ApiResponse::success(__('profile.session_revoked'));
    }
}
