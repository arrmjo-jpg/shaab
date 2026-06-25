<?php

declare(strict_types=1);

namespace App\Actions\Public\Notification;

use App\Http\Resources\Public\NotificationResource;
use App\Models\User;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * قائمة إشعارات المستخدم نفسه (database notifications). محصورة بعلاقة Notifiable
 * (notifiable_id = الفاعل) فلا يرى إشعارات غيره. الأحدث أولاً (ترتيب العلاقة).
 * فلتر اختياري ?filter[read]=0 (غير المقروء) أو 1 (المقروء). meta.pagination
 * يحمل أيضاً unread (عدّاد غير المقروء) لتغذية الجرس من نداء واحد.
 */
class ListMyNotificationsAction
{
    public function handle(User $actor, Request $request): JsonResponse
    {
        $default = (int) config('performance.pagination.default');
        $max = (int) config('performance.pagination.max');
        $perPage = max(1, min((int) $request->integer('per_page', $default), $max));

        $query = $actor->notifications();

        $read = $request->input('filter.read');
        if ($read === '0' || $read === 0) {
            $query->whereNull('read_at');
        } elseif ($read === '1' || $read === 1) {
            $query->whereNotNull('read_at');
        }

        $paginator = $query->paginate($perPage)->appends($request->query());

        return ApiResponse::success(
            data: NotificationResource::collection($paginator)->resolve(),
            meta: ['pagination' => [
                'total' => $paginator->total(),
                'count' => $paginator->count(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'total_pages' => $paginator->lastPage(),
                'unread' => $actor->unreadNotifications()->count(),
            ]],
        );
    }
}
