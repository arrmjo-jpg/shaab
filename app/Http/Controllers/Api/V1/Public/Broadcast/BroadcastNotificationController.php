<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public\Broadcast;

use App\Http\Controllers\Controller;
use App\Models\Broadcast;
use App\Models\BroadcastNotificationSubscription;
use App\Support\Broadcast\BroadcastPresenceControl;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * تفضيلات إشعارات البثّ (B8) — للمُصادَقين فقط (الزوّار يُرفَضون عبر middleware). نوعان:
 *   • عام: «أعلِمني بالبثوث المباشرة» (broadcast_id = null).
 *   • تذكير حدثٍ بعينه: «ذكّرني بهذا البثّ» (broadcast_id != null).
 *
 * idempotent: firstOrCreate/delete ⇒ اشتراكات مكرّرة لا تتراكم. التسليم لاحقاً عبر
 * مواضيع FCM (نشر واحد للموضوع). الاشتراك يخزّن التفضيل (للأهليّة/الجدولة/الحالة).
 */
class BroadcastNotificationController extends Controller
{
    // ─── الإشعار العام بالبثوث المباشرة ──────────────────────────────
    public function subscribeLive(Request $request): JsonResponse
    {
        BroadcastNotificationSubscription::firstOrCreate([
            'user_id' => (int) $request->user()->id,
            'broadcast_id' => null,
        ]);

        return ApiResponse::success(__('broadcast.notification.live_subscribed'), ['subscribed' => true]);
    }

    public function unsubscribeLive(Request $request): JsonResponse
    {
        BroadcastNotificationSubscription::query()
            ->forUser((int) $request->user()->id)->global()->delete();

        return ApiResponse::success(__('broadcast.notification.live_unsubscribed'), ['subscribed' => false]);
    }

    public function liveStatus(Request $request): JsonResponse
    {
        return ApiResponse::success(data: [
            'subscribed' => BroadcastNotificationSubscription::query()
                ->forUser((int) $request->user()->id)->global()->exists(),
        ]);
    }

    // ─── تذكير حدثٍ بعينه ─────────────────────────────────────────────
    public function subscribeReminder(Request $request, Broadcast $broadcast): JsonResponse
    {
        if (! BroadcastPresenceControl::isPubliclyVisible($broadcast->status->value, (bool) $broadcast->is_public)) {
            return ApiResponse::error(__('broadcast.not_found'), [], 404);
        }

        BroadcastNotificationSubscription::firstOrCreate([
            'user_id' => (int) $request->user()->id,
            'broadcast_id' => $broadcast->id,
        ]);

        return ApiResponse::success(__('broadcast.notification.reminder_subscribed'), ['subscribed' => true]);
    }

    public function unsubscribeReminder(Request $request, Broadcast $broadcast): JsonResponse
    {
        BroadcastNotificationSubscription::query()
            ->forUser((int) $request->user()->id)->forBroadcast($broadcast->id)->delete();

        return ApiResponse::success(__('broadcast.notification.reminder_unsubscribed'), ['subscribed' => false]);
    }

    public function reminderStatus(Request $request, Broadcast $broadcast): JsonResponse
    {
        return ApiResponse::success(data: [
            'subscribed' => BroadcastNotificationSubscription::query()
                ->forUser((int) $request->user()->id)->forBroadcast($broadcast->id)->exists(),
        ]);
    }
}
