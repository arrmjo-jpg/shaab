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
 * إعادة فتح جمهور بثّ مُغلَق — يُلغي علم الإغلاق فيُسمح بالانضمام/النبض من جديد. لا
 * يمسّ دورة الحياة (إن كان البثّ متوقّفاً يبقى offline حتى يُستأنف عبر B2). idempotent.
 */
class ReopenAudienceAction
{
    public function handle(Broadcast $broadcast, ?User $actor = null): JsonResponse
    {
        BroadcastPresenceControl::reopen($broadcast->id);
        BroadcastModerationAudit::log('audience_reopened', $actor, $broadcast);

        return ApiResponse::success(__('broadcast.moderation.reopened'));
    }
}
