<?php

declare(strict_types=1);

namespace App\Actions\Public\Auth;

use App\Models\User;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class LogoutAction
{
    public function handle(User $user): JsonResponse
    {
        // يُلغى الـ token الحالي فقط — لا تُمس باقي الجلسات
        $user->currentAccessToken()->delete();

        return ApiResponse::success(__('auth.logout_success'));
    }
}
