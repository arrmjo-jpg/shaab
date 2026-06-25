<?php

declare(strict_types=1);

namespace App\Actions\Admin\Users;

use App\Models\User;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class DeleteUserAction
{
    public function handle(User $target, User $actor): JsonResponse
    {
        // لا يمكن للمدير حذف حسابه
        if ($target->id === $actor->id) {
            return ApiResponse::error(__('user.cannot_delete_self'), [], 403);
        }

        // حساب مدير النظام محمي من الحذف
        if ($target->hasRole('super_admin')) {
            return ApiResponse::error(__('user.cannot_delete_super_admin'), [], 403);
        }

        $target->delete();

        return ApiResponse::success(__('user.deleted'));
    }
}
