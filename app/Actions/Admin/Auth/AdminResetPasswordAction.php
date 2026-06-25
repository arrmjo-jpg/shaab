<?php

declare(strict_types=1);

namespace App\Actions\Admin\Auth;

use App\Models\User;
use App\Support\Responses\ApiResponse;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class AdminResetPasswordAction
{
    public function handle(array $validated): JsonResponse
    {
        $status = Password::reset(
            $validated,
            function (User $user, string $password): void {
                // تشديد أمني: تدوير remember_token + إبطال كل جلسات Sanctum.
                // إعادة التعيين تُستخدَم لاستعادة حساب قد يكون مخترَقاً — يجب أن
                // تموت كل الجلسات القائمة فوراً (بما فيها أي توكن مسروق).
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();
                $user->tokens()->delete();
                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return ApiResponse::error(__($status), [], 422);
        }

        return ApiResponse::success(__('auth.reset_password_success'));
    }
}
