<?php

declare(strict_types=1);

namespace App\Actions\Admin\Profile;

use App\Http\Resources\Admin\Profile\ProfileResource;
use App\Models\User;
use App\Support\Auth\AuthActivity;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class UpdateProfileAction
{
    /** الحقول الوحيدة القابلة للتعديل ذاتياً. */
    private const ALLOWED = ['name', 'bio', 'avatar', 'social_links'];

    public function handle(User $user, array $validated): JsonResponse
    {
        foreach (self::ALLOWED as $field) {
            if (array_key_exists($field, $validated)) {
                $user->{$field} = $validated[$field];
            }
        }
        $user->save();
        AuthActivity::log('profile_updated', $user);

        return ApiResponse::success(
            __('profile.updated'),
            new ProfileResource($user->load('roles'))
        );
    }
}
