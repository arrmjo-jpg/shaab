<?php

declare(strict_types=1);

namespace App\Actions\Public\Auth;

use App\Enums\UserStatus;
use App\Http\Resources\Public\Auth\AuthResource;
use App\Models\User;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class RegisterAction
{
    public function handle(array $validated, string $ip): JsonResponse
    {
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'status' => UserStatus::Active,
        ]);

        $user->assignRole('user');
        $user->recordLogin($ip);

        $token = $user->createToken('public-token', ['user'])->plainTextToken;

        return ApiResponse::success(
            __('auth.register_success'),
            new AuthResource($user, $token),
            201
        );
    }
}
