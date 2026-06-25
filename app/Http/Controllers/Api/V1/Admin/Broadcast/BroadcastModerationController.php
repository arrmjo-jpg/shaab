<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Broadcast;

use App\Actions\Admin\Broadcast\BanViewerAction;
use App\Actions\Admin\Broadcast\CloseAudienceAction;
use App\Actions\Admin\Broadcast\EmergencyShutdownAction;
use App\Actions\Admin\Broadcast\KickViewerAction;
use App\Actions\Admin\Broadcast\ReopenAudienceAction;
use App\Actions\Admin\Broadcast\UnbanViewerAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Broadcast\BanViewerRequest;
use App\Http\Requests\Admin\Broadcast\BroadcastViewerTargetRequest;
use App\Models\Broadcast;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * الإشراف على جمهور البثّ (B6) — طبقة تحكّم إداريّة فوق محرّك الحضور التعاونيّ (B5).
 * كل التحكّم تعاونيّ عبر حالة النبضة (لا قطع بايت — البثّ مصدر خارجي). كل نقطة محميّة
 * بصلاحية حبيبيّة ومُدقَّقة بالفاعل. الهدف يُحلّ عبر user_id (قويّ) أو member (أفضل-جهد).
 */
class BroadcastModerationController extends Controller
{
    public function kick(BroadcastViewerTargetRequest $request, Broadcast $broadcast): JsonResponse
    {
        return (new KickViewerAction)->handle($broadcast, $request->resolvedMember(), $request->user());
    }

    public function ban(BanViewerRequest $request, Broadcast $broadcast): JsonResponse
    {
        return (new BanViewerAction)->handle(
            $broadcast,
            $request->resolvedMember(),
            $request->integer('duration_minutes') ?: null,
            $request->string('reason')->value() ?: null,
            $request->user(),
        );
    }

    public function unban(BroadcastViewerTargetRequest $request, Broadcast $broadcast): JsonResponse
    {
        return (new UnbanViewerAction)->handle($broadcast, $request->resolvedMember(), $request->user());
    }

    public function close(Request $request, Broadcast $broadcast): JsonResponse
    {
        return (new CloseAudienceAction)->handle($broadcast, $request->user());
    }

    public function reopen(Request $request, Broadcast $broadcast): JsonResponse
    {
        return (new ReopenAudienceAction)->handle($broadcast, $request->user());
    }

    public function emergencyShutdown(Request $request, Broadcast $broadcast): JsonResponse
    {
        return (new EmergencyShutdownAction)->handle($broadcast, $request->user());
    }
}
