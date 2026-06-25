<?php

declare(strict_types=1);

namespace App\Actions\Admin\Auth;

use App\Models\User;
use App\Notifications\VerifyAdminEmail;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class SendAdminEmailVerificationAction
{
    public function handle(string $email): JsonResponse
    {
        $user = User::where('email', $email)->first();

        // يُرسَل فقط لإداري موجود وغير مؤكَّد — واستجابة موحّدة دائماً
        // (حماية من كشف وجود الحساب).
        if ($user !== null && $user->isAdmin() && $user->email_verified_at === null) {
            $user->notify(new VerifyAdminEmail);
        }

        return ApiResponse::success(__('auth.verify_email.sent'));
    }
}
