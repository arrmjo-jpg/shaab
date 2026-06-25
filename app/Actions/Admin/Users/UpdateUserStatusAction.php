<?php

declare(strict_types=1);

namespace App\Actions\Admin\Users;

use App\Http\Resources\Admin\Users\UserResource;
use App\Models\User;
use App\Support\Frontend\FrontendRevalidate;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class UpdateUserStatusAction
{
    public function handle(User $target, User $actor, string $status): JsonResponse
    {
        // منع قفل الذات: لا يمكن للمدير تغيير حالة حسابه
        if ($target->id === $actor->id) {
            return ApiResponse::error(__('user.cannot_change_own_status'), [], 403);
        }

        $target->update(['status' => $status]);

        // بوّابة بروفايل الكاتب العامّ = is_writer + Active ⇒ تغيّر الحالة يقلب الظهور.
        FrontendRevalidate::tags(['writers', "writer:{$target->id}"]);

        return ApiResponse::success(
            __('user.status_updated'),
            new UserResource($target->load('roles'))
        );
    }
}
