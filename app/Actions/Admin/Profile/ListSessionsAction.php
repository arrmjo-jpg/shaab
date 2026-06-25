<?php

declare(strict_types=1);

namespace App\Actions\Admin\Profile;

use App\Http\Resources\Admin\Profile\ProfileSessionResource;
use App\Models\User;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class ListSessionsAction
{
    public function handle(User $user, int|string|null $currentTokenId): JsonResponse
    {
        $tokens = $user->tokens()->latest('last_used_at')->get();

        return ApiResponse::success(
            data: ProfileSessionResource::for($tokens, (int) $currentTokenId)
        );
    }
}
