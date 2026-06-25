<?php

declare(strict_types=1);

namespace App\Actions\Public\Broadcast;

use App\Support\Broadcast\BroadcastPresence;
use App\Support\Broadcast\BroadcastPresenceControl;
use App\Support\Broadcast\PresenceSessionToken;
use App\Support\Engagement\BotSignature;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * نبضة الحضور: تتحقّق من الرمز الموقّع، تحسم حالة التحكّم التعاوني، وتُسجّل الحضور
 * (الكاش فقط — لا كتابة قاعدة بيانات) متى كانت allowed وغير بوت. عند حالة غير allowed
 * لا تُسجّل (خروج تعاوني: العميل يفكّ الارتباط ويسقط من العدّ في الدلو التالي تلقائياً —
 * فلا حضور شبحيّ). رمز غير صالح/منتهٍ ⇒ 422 فيعيد العميل الانضمام (إعادة اتصال آمنة).
 */
class HeartbeatPresenceAction
{
    public function handle(int $broadcastId, string $token, Request $request): JsonResponse
    {
        $payload = PresenceSessionToken::verify($token, $broadcastId);
        if ($payload === null) {
            return ApiResponse::error(__('broadcast.presence.invalid_token'), [], 422);
        }

        $snapshot = BroadcastPresence::snapshot($broadcastId);
        if ($snapshot === null) {
            return ApiResponse::error(__('broadcast.not_found'), [], 404);
        }

        $member = $payload['member'];
        $state = BroadcastPresenceControl::resolve($snapshot['status'], $snapshot['is_public'], $broadcastId, $member);

        // يُحتسَب فقط متى كان مسموحاً وغير بوت (منع تضخيم العدّاد بالبوتات).
        if ($state->isPresent() && ! BotSignature::isBot($request->userAgent())) {
            BroadcastPresence::touch($broadcastId, $member);
        }

        return ApiResponse::success(data: [
            'state' => $state->value,
            'status' => $snapshot['status'],
            'viewers_now' => BroadcastPresence::viewersNow($snapshot['status'], $broadcastId),
            'heartbeat_interval' => BroadcastPresence::interval(),
        ])->header('Cache-Control', 'no-store, max-age=0');
    }
}
