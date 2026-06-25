<?php

declare(strict_types=1);

namespace App\Actions\Admin\Profile;

use App\Http\Resources\Admin\Profile\ProfileResource;
use App\Models\User;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class ShowProfileAction
{
    public function handle(User $user): JsonResponse
    {
        return ApiResponse::success(
            data: new ProfileResource($user->load('roles'))
        );
    }
}
