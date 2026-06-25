<?php

declare(strict_types=1);

namespace App\Actions\Admin\Users;

use App\Models\User;
use App\Support\Auth\PasswordResetAudit;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;

class SendUserPasswordResetAction
{
    public function handle(User $target): JsonResponse
    {
        PasswordResetAudit::record($target->email, $target);

        $status = Password::sendResetLink(['email' => $target->email]);

        if ($status === Password::RESET_THROTTLED) {
            return ApiResponse::error(__('passwords.throttled'), [], 429);
        }

        if ($status !== Password::RESET_LINK_SENT) {
            return ApiResponse::error(__('user.password_reset_failed'), [], 422);
        }

        return ApiResponse::success(__('user.password_reset_sent'));
    }
}
