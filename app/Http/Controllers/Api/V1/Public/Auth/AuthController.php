<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public\Auth;

use App\Actions\Public\Auth\ForgotPasswordAction;
use App\Actions\Public\Auth\LoginAction;
use App\Actions\Public\Auth\LogoutAction;
use App\Actions\Public\Auth\RegisterAction;
use App\Actions\Public\Auth\ResetPasswordAction;
use App\Actions\Public\Auth\UpdateProfileAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\Auth\ForgotPasswordRequest;
use App\Http\Requests\Public\Auth\LoginRequest;
use App\Http\Requests\Public\Auth\RegisterRequest;
use App\Http\Requests\Public\Auth\ResetPasswordRequest;
use App\Http\Requests\Public\Auth\UpdateAvatarRequest;
use App\Http\Requests\Public\Auth\UpdateProfileRequest;
use App\Http\Resources\Public\UserResource;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        return (new RegisterAction)->handle($request->validated(), $request->ip());
    }

    public function login(LoginRequest $request): JsonResponse
    {
        return (new LoginAction)->handle($request->validated(), $request->ip());
    }

    public function logout(Request $request): JsonResponse
    {
        return (new LogoutAction)->handle($request->user());
    }

    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success(data: new UserResource($request->user()));
    }

    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        return (new UpdateProfileAction)->handle($request->user(), $request->validated());
    }

    public function updateAvatar(UpdateAvatarRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->addMediaFromRequest('avatar')->toMediaCollection('avatar');

        return ApiResponse::success(data: new UserResource($user->fresh()));
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        return (new ForgotPasswordAction)->handle($request->validated('email'));
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        return (new ResetPasswordAction)->handle($request->validated());
    }
}
