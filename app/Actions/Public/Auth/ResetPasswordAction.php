<?php

declare(strict_types=1);

namespace App\Actions\Public\Auth;

use App\Models\User;
use App\Support\Responses\ApiResponse;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class ResetPasswordAction
{
    public function handle(array $validated): JsonResponse
    {
        $status = Password::reset(
            $validated,
            function (User $user, string $password): void {
                // تشديد أمني: تدوير remember_token + إبطال كل توكنات Sanctum
                // عند الاستعادة — تموت كل الجلسات القائمة فوراً.
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();
                $user->tokens()->delete();
                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            // $status مفتاح ترجمة من passwords.php (مثل passwords.token)
            return ApiResponse::error(__($status), [], 422);
        }

        return ApiResponse::success(__('auth.reset_password_success'));
    }
}
