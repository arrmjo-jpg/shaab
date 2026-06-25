<?php

declare(strict_types=1);

namespace App\Actions\Public\Auth;

use App\Http\Resources\Public\UserResource;
use App\Models\User;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * تحديث الملف الشخصيّ للمستخدم المُصادَق (الاسم/النبذة/روابط التواصل). عبر Eloquent (مُدقَّق).
 * لا يمسّ البريد/الدور/الحالة. الصورة تُدار عبر شريحة وسائط مستقلّة.
 */
class UpdateProfileAction
{
    public function handle(User $user, array $validated): JsonResponse
    {
        $data = [];

        if (array_key_exists('name', $validated)) {
            $data['name'] = $validated['name'];
        }

        if (array_key_exists('bio', $validated)) {
            $data['bio'] = $validated['bio'];
        }

        if (array_key_exists('social_links', $validated)) {
            $links = array_filter(
                $validated['social_links'] ?? [],
                static fn ($v): bool => is_string($v) && $v !== '',
            );
            $data['social_links'] = $links === [] ? null : $links;
        }

        if ($data !== []) {
            $user->update($data);
        }

        return ApiResponse::success(data: new UserResource($user->fresh()));
    }
}
