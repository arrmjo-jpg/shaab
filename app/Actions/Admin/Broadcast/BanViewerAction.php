<?php

declare(strict_types=1);

namespace App\Actions\Admin\Broadcast;

use App\Models\Broadcast;
use App\Models\User;
use App\Support\Audit\BroadcastModerationAudit;
use App\Support\Broadcast\BroadcastPresenceControl;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * حظر مؤقّت لمشاهد — يمنع إعادة الاتصال والنبضة طوال سريانه (resolve يُعيد banned في
 * join وheartbeat معاً، فلا يُحتسَب ولا يعود). الانتهاء تلقائيّ عبر TTL (لا مهمّة
 * تنظيف). المُصادَق إنفاذ قويّ (هوية u{id})؛ الزائر أفضل-جهد فقط (هوية بصمة قد يلتفّ
 * حولها بمسح التخزين/تغيير العميل — لا ندّعي هوية زائر مثالية).
 */
class BanViewerAction
{
    public function handle(
        Broadcast $broadcast,
        string $member,
        ?int $durationMinutes,
        ?string $reason,
        ?User $actor = null,
    ): JsonResponse {
        $max = max(1, (int) config('broadcast.presence.max_ban_minutes', 10080));
        $default = max(1, (int) config('broadcast.presence.default_ban_minutes', 60));
        $minutes = min($max, max(1, $durationMinutes ?? $default));
        $expiresAt = now()->addMinutes($minutes);

        BroadcastPresenceControl::ban($broadcast->id, $member, $minutes * 60, [
            'reason' => $reason,
            'by' => $actor?->id,
            'expires_at' => $expiresAt->toISOString(),
        ]);

        BroadcastModerationAudit::log('viewer_banned', $actor, $broadcast, [
            'member' => $member,
            'reason' => $reason,
            'duration_minutes' => $minutes,
            'expires_at' => $expiresAt->toISOString(),
        ]);

        return ApiResponse::success(__('broadcast.moderation.banned'), [
            'member' => $member,
            'reason' => $reason,
            'duration_minutes' => $minutes,
            'expires_at' => $expiresAt->toISOString(),
        ]);
    }
}
