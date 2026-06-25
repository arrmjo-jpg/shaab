<?php

declare(strict_types=1);

namespace App\Actions\Public\Follow;

use App\Enums\FollowableType;
use App\Models\Follow;
use App\Models\User;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * قائمة «أتابعهم» للمستخدم — أزواج (type, id) فقط؛ الأسماء/الشعارات تُحلّ في الواجهة من 365
 * (لا نخزّنها محليّاً — مصدرها الوحيد 365). تصفية اختياريّة بالنوع. الأحدث أوّلاً.
 */
class ListFollowsAction
{
    public function handle(User $user, ?FollowableType $type = null): JsonResponse
    {
        $follows = Follow::query()
            ->forUser($user->id)
            ->when($type !== null, fn ($q) => $q->ofType($type))
            ->orderByDesc('id')
            ->get(['followable_type', 'followable_id']);

        return ApiResponse::success(data: [
            'follows' => $follows
                ->map(fn (Follow $f): array => ['type' => $f->followable_type->value, 'id' => $f->followable_id])
                ->all(),
        ]);
    }
}
