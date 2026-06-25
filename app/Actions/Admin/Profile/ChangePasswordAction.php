<?php

declare(strict_types=1);

namespace App\Actions\Admin\Profile;

use App\Models\User;
use App\Support\Auth\AuthActivity;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class ChangePasswordAction
{
    /**
     * @param  int|string|null  $currentTokenId  توكن الجلسة الحالية (يبقى صالحاً)
     */
    public function handle(User $user, array $validated, int|string|null $currentTokenId): JsonResponse
    {
        if (! Hash::check($validated['current_password'], $user->password)) {
            return ApiResponse::error(
                __('profile.current_password_invalid'),
                ['current_password' => [__('profile.current_password_invalid')]],
                422
            );
        }

        $user->password = $validated['password']; // cast hashed
        $user->save();

        AuthActivity::log('password_changed', $user);

        // تشديد أمني: إلغاء كل التوكنات الأخرى، وإبقاء الجلسة الحالية
        $user->tokens()
            ->when($currentTokenId !== null, fn ($q) => $q->where('id', '!=', $currentTokenId))
            ->delete();

        return ApiResponse::success(__('profile.password_changed'));
    }
}
