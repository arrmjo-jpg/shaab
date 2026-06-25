<?php

declare(strict_types=1);

namespace App\Actions\Public\Follow;

use App\Enums\FollowableType;
use App\Models\Follow;
use App\Models\User;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * متابعة/إلغاء متابعة كيان رياضيّ (toggle) — idempotent: متابِعٌ أصلاً ⇒ إلغاء، وإلا متابعة.
 * يمرّ عبر Eloquent (create/delete) كي يُسجَّل في activity_log (AuditsChanges). قيد الفرادة
 * يحمي من السباق (نقر مزدوج): إنشاء مكرّر يفشل بأمان فيُعامَل كأنّه متابَع.
 */
class ToggleFollowAction
{
    public function handle(User $user, FollowableType $type, int $id): JsonResponse
    {
        $existing = Follow::query()
            ->forUser($user->id)
            ->ofType($type)
            ->where('followable_id', $id)
            ->first();

        if ($existing !== null) {
            $existing->delete();

            return ApiResponse::success(message: __('follow.unfollowed'), data: ['following' => false]);
        }

        Follow::create([
            'user_id' => $user->id,
            'followable_type' => $type->value,
            'followable_id' => $id,
        ]);

        return ApiResponse::success(message: __('follow.followed'), data: ['following' => true]);
    }
}
