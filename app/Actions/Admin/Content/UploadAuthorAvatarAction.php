<?php

declare(strict_types=1);

namespace App\Actions\Admin\Content;

use App\Models\User;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;

class UploadAuthorAvatarAction
{
    public function handle(User $user, UploadedFile $file): JsonResponse
    {
        $user->clearMediaCollection('avatar');
        $user->addMedia($file)->toMediaCollection('avatar');

        return ApiResponse::success(__('media.uploaded'), [
            'user_id' => $user->id,
            'avatar' => $user->getFirstMediaUrl('avatar'),
            'avatar_thumb' => $user->getFirstMediaUrl('avatar', 'thumb'),
        ], 201);
    }
}
