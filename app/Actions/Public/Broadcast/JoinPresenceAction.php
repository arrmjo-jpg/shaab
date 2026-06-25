<?php

declare(strict_types=1);

namespace App\Actions\Public\Broadcast;

use App\Enums\BroadcastPresenceState;
use App\Support\Broadcast\BroadcastPresence;
use App\Support\Broadcast\BroadcastPresenceControl;
use App\Support\Broadcast\PresenceSessionToken;
use App\Support\Engagement\EngagementActor;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * الانضمام للحضور: يُصدِر رمز جلسة موقّعاً لهوية العضو (مُصادَق "u{id}" أو زائر بصمة
 * "f{hash}" — هوية هجينة مرآة EngagementActor)، ويعيد الحالة الأولية + العدّ التقريبي
 * + تواتر النبضة. لا كاش (no-store) — استجابة خاصّة بالفاعل. غير مرئي عموماً ⇒ 404.
 */
class JoinPresenceAction
{
    public function handle(int $broadcastId, Request $request): JsonResponse
    {
        $snapshot = BroadcastPresence::snapshot($broadcastId);
        if ($snapshot === null) {
            return ApiResponse::error(__('broadcast.not_found'), [], 404);
        }

        $actor = EngagementActor::fromRequest($request);
        $member = $actor->key();
        $type = $actor->userId !== null ? 'auth' : 'guest';

        $state = BroadcastPresenceControl::resolve($snapshot['status'], $snapshot['is_public'], $broadcastId, $member);
        if ($state === BroadcastPresenceState::Unavailable) {
            return ApiResponse::error(__('broadcast.not_found'), [], 404);
        }

        // يُسجَّل الحضور فوراً متى كان مسموحاً وغير بوت (تجربة عدّاد فورية + منع تضخيم البوتات).
        if ($state->isPresent() && ! $actor->isBot) {
            BroadcastPresence::touch($broadcastId, $member);
        }

        return ApiResponse::success(data: [
            'token' => PresenceSessionToken::issue($broadcastId, $member, $type),
            'state' => $state->value,
            'status' => $snapshot['status'],
            'viewers_now' => BroadcastPresence::viewersNow($snapshot['status'], $broadcastId),
            'heartbeat_interval' => BroadcastPresence::interval(),
        ])->header('Cache-Control', 'no-store, max-age=0');
    }
}
