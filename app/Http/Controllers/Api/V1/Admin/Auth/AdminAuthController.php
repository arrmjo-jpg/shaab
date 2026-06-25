<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Auth;

use App\Actions\Admin\Auth\AdminForgotPasswordAction;
use App\Actions\Admin\Auth\AdminLoginAction;
use App\Actions\Admin\Auth\AdminLogoutAction;
use App\Actions\Admin\Auth\AdminResetPasswordAction;
use App\Actions\Admin\Auth\SendAdminEmailVerificationAction;
use App\Actions\Admin\Auth\VerifyAdminEmailAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Auth\AdminForgotPasswordRequest;
use App\Http\Requests\Admin\Auth\AdminLoginRequest;
use App\Http\Requests\Admin\Auth\AdminResendVerificationRequest;
use App\Http\Requests\Admin\Auth\AdminResetPasswordRequest;
use App\Http\Resources\Admin\AdminUserResource;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdminAuthController extends Controller
{
    public function login(AdminLoginRequest $request): JsonResponse
    {
        return (new AdminLoginAction)->handle($request->validated(), $request->ip());
    }

    public function logout(Request $request): JsonResponse
    {
        return (new AdminLogoutAction)->handle($request->user());
    }

    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success(data: new AdminUserResource($request->user()));
    }

    public function forgotPassword(AdminForgotPasswordRequest $request): JsonResponse
    {
        return (new AdminForgotPasswordAction)->handle($request->validated('email'));
    }

    public function resetPassword(AdminResetPasswordRequest $request): JsonResponse
    {
        return (new AdminResetPasswordAction)->handle($request->validated());
    }

    public function resendEmailVerification(AdminResendVerificationRequest $request): JsonResponse
    {
        return (new SendAdminEmailVerificationAction)->handle($request->validated('email'));
    }

    public function verifyEmail(Request $request, int $id, string $hash): RedirectResponse
    {
        return (new VerifyAdminEmailAction)->handle($id, $hash);
    }
}
