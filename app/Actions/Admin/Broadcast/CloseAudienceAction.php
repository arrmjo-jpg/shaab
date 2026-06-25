<?php

declare(strict_types=1);

namespace App\Actions\Admin\Broadcast;

use App\Models\Broadcast;
use App\Models\User;
use App\Support\Audit\BroadcastModerationAudit;
use App\Support\Broadcast\BroadcastPresence;
use App\Support\Broadcast\BroadcastPresenceControl;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * إغلاق جمهور البثّ بالكامل — كل المشاهدين يتلقّون closed في النبضة التالية، ولا
 * انضمام جديد (resolve يُعيد closed في join). Redis-backed (فوري، لا استطلاع DB).
 * يصفّر العدّ فوراً (تفكيك تعاونيّ). لا يمسّ دورة الحياة — يُرفَع بـ reopen صراحةً.
 */
class CloseAudienceAction
{
    public function handle(Broadcast $broadcast, ?User $actor = null): JsonResponse
    {
        BroadcastPresenceControl::close($broadcast->id);
        BroadcastPresence::reset($broadcast->id);
        BroadcastModerationAudit::log('audience_closed', $actor, $broadcast);

        return ApiResponse::success(__('broadcast.moderation.closed'));
    }
}
