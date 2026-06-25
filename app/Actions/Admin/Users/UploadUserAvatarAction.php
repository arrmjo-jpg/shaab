<?php

declare(strict_types=1);

namespace App\Actions\Admin\Users;

use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * يرفع صورة مستخدم على قرص public ويعيد المسار + الرابط.
 * مستقل عن إنشاء المستخدم: يُستخدم في الإضافة والتعديل معاً.
 */
class UploadUserAvatarAction
{
    public function handle(UploadedFile $file): JsonResponse
    {
        $uuid = (string) Str::uuid();
        $extension = strtolower($file->getClientOriginalExtension());
        $path = Storage::disk('public')->putFileAs('avatars', $file, "{$uuid}.{$extension}");

        return ApiResponse::success(
            __('user.avatar_uploaded'),
            [
                'path' => $path,
                'url' => Storage::disk('public')->url($path),
            ]
        );
    }
}
