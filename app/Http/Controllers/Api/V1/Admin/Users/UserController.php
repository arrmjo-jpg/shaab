<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Users;

use App\Actions\Admin\Users\CreateUserAction;
use App\Actions\Admin\Users\DeleteUserAction;
use App\Actions\Admin\Users\ListUsersAction;
use App\Actions\Admin\Users\RestoreUserAction;
use App\Actions\Admin\Users\SendUserPasswordResetAction;
use App\Actions\Admin\Users\UpdateUserAction;
use App\Actions\Admin\Users\UpdateUserStatusAction;
use App\Actions\Admin\Users\UploadUserAvatarAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Users\StoreUserRequest;
use App\Http\Requests\Admin\Users\UpdateUserRequest;
use App\Http\Requests\Admin\Users\UpdateUserStatusRequest;
use App\Http\Requests\Admin\Users\UploadUserAvatarRequest;
use App\Http\Resources\Admin\Users\UserResource;
use App\Models\User;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(): JsonResponse
    {
        return (new ListUsersAction)->handle();
    }

    public function show(User $user): JsonResponse
    {
        return ApiResponse::success(data: new UserResource($user->load('roles')));
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        return (new CreateUserAction)->handle($request->validated(), $request->user());
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        return (new UpdateUserAction)->handle($user, $request->user(), $request->validated());
    }

    public function updateStatus(UpdateUserStatusRequest $request, User $user): JsonResponse
    {
        return (new UpdateUserStatusAction)->handle(
            $user,
            $request->user(),
            $request->validated('status')
        );
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        return (new DeleteUserAction)->handle($user, $request->user());
    }

    public function restore(User $user): JsonResponse
    {
        return (new RestoreUserAction)->handle($user);
    }

    public function sendPasswordReset(User $user): JsonResponse
    {
        return (new SendUserPasswordResetAction)->handle($user);
    }

    public function uploadAvatar(UploadUserAvatarRequest $request): JsonResponse
    {
        return (new UploadUserAvatarAction)->handle($request->file('avatar'));
    }
}
