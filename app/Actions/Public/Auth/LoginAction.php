<?php

declare(strict_types=1);

namespace App\Actions\Public\Auth;

use App\Enums\UserStatus;
use App\Http\Resources\Public\Auth\AuthResource;
use App\Models\User;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class LoginAction
{
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

        $user->recordLogin($ip);

        $token = $user->createToken('public-token', ['user'])->plainTextToken;

        return ApiResponse::success(
            __('auth.login_success'),
            new AuthResource($user, $token)
        );
    }
}
