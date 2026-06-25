<?php

declare(strict_types=1);

namespace App\Actions\Admin\Auth;

use App\Models\User;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class AdminLogoutAction
{
    public function handle(User $user): JsonResponse
    {
        // يُلغى الـ token الحالي فقط — لا تُمس باقي الجلسات الإدارية
        $user->currentAccessToken()->delete();

        return ApiResponse::success(__('auth.logout_success'));
    }
}
