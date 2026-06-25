<?php

declare(strict_types=1);

namespace App\Actions\Admin\Auth;

use App\Models\User;
use App\Support\Auth\PasswordResetAudit;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;

class AdminForgotPasswordAction
{
    public function handle(string $email): JsonResponse
    {
        PasswordResetAudit::record($email, User::where('email', $email)->first());

        $status = Password::sendResetLink(['email' => $email]);

        // حماية من كشف وجود المستخدم: نفس استجابة النجاح
        // سواء أُرسل الرابط أو كان الإيميل غير موجود
        if ($status === Password::RESET_THROTTLED) {
            return ApiResponse::error(__('passwords.throttled'), [], 429);
        }

        return ApiResponse::success(__('auth.forgot_password_sent'));
    }
}
