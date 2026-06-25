<?php

declare(strict_types=1);

namespace App\Actions\Public\Notification;

use App\Models\User;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * تحديد إشعار واحد كمقروء — محصور بملكية الـ notifiable (المستخدم نفسه): البحث
 * ضمن علاقته فقط، فإشعار غيره غير موجود في النطاق → 404 (لا تسريب وجود/403).
 */
class MarkNotificationReadAction
{
    public function handle(User $actor, string $notificationId): JsonResponse
    {
        $notification = $actor->notifications()->whereKey($notificationId)->first();

        if ($notification === null) {
            return ApiResponse::error(__('notification.not_found'), [], 404);
        }

        $notification->markAsRead();

        return ApiResponse::success(__('notification.marked_read'));
    }
}
