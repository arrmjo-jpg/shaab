<?php

declare(strict_types=1);

namespace App\Actions\Admin\Chat;

use App\Models\User;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * جهات اتصال الشات — قائمة المدراء (أصحاب أدوار اللوحة) لاختيار مُستقبِل DM/مجموعة.
 * متاحة لكل أدمن (لا صلاحية users.view) — الشات بلا صلاحيات. تُعيد الحدّ الأدنى فقط.
 */
class ListChatContactsAction
{
    /** أدوار اللوحة المؤهَّلة كجهات اتصال (مطابقة لحارس مجموعة مسارات admin). */
    private const ADMIN_ROLES = [
        'super_admin', 'editor', 'reviewer', 'moderator',
        'social_media_manager', 'journalist', 'contributor',
    ];

    public function handle(User $actor, Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));

        $contacts = User::query()
            ->where('id', '!=', $actor->id)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', self::ADMIN_ROLES))
            ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->limit(50)
            ->get(['id', 'name', 'avatar']);

        return ApiResponse::success(
            data: $contacts->map(fn (User $u): array => [
                'id' => $u->id,
                'name' => $u->name,
                'avatar' => $u->avatar,
            ])->all(),
        );
    }
}
