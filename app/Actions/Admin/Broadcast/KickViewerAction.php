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
 * طرد مشاهد (≠ حظر): «غادر الآن». النبضة التالية تُعيد kicked فيفكّ العميل المشغّل
 * تعاونياً ويسقط من العدّ. طردٌ مؤقّت بعمر (kick_ttl) — يجوز للمشاهد العودة بعده ما لم
 * يُحظَر منفصلاً. تعاونيّ فقط (لا قطع بايت — البثّ مصدر خارجي).
 */
class KickViewerAction
{
    public function handle(Broadcast $broadcast, string $member, ?User $actor = null): JsonResponse
    {
        BroadcastPresenceControl::kick($broadcast->id, $member);
        BroadcastModerationAudit::log('viewer_kicked', $actor, $broadcast, ['member' => $member]);

        return ApiResponse::success(__('broadcast.moderation.kicked'), ['member' => $member]);
    }
}
