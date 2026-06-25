<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Content;

use App\Actions\Admin\Content\UploadAuthorAvatarAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Content\UploadAuthorAvatarRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class AuthorMediaController extends Controller
{
    public function avatar(UploadAuthorAvatarRequest $request, User $user): JsonResponse
    {
        return (new UploadAuthorAvatarAction)->handle($user, $request->file('avatar'));
    }
}
