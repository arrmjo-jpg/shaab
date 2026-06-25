<?php

declare(strict_types=1);

namespace App\Actions\Public\Notification;

use App\Models\User;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

/**
 * تحديد كل إشعارات المستخدم غير المقروءة كمقروءة دفعةً واحدة (تحديث جماعي على
 * نطاق علاقته فقط). الإشعارات ليست كياناً مُدقَّقاً (لا AuditsChanges) فالتحديث
 * الجماعي مقبول هنا.
 */
class MarkAllNotificationsReadAction
{
    public function handle(User $actor): JsonResponse
    {
        $actor->unreadNotifications()->update(['read_at' => Carbon::now()]);

        return ApiResponse::success(__('notification.marked_all_read'));
    }
}
