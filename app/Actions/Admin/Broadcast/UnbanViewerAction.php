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
 * رفع الحظر مبكّراً عن مشاهد (قبل انتهاء عمره التلقائي). idempotent: رفع حظر غير
 * قائم لا يضرّ.
 */
class UnbanViewerAction
{
    public function handle(Broadcast $broadcast, string $member, ?User $actor = null): JsonResponse
    {
        BroadcastPresenceControl::unban($broadcast->id, $member);
        BroadcastModerationAudit::log('viewer_unbanned', $actor, $broadcast, ['member' => $member]);

        return ApiResponse::success(__('broadcast.moderation.unbanned'), ['member' => $member]);
    }
}
