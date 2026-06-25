<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public\Broadcast;

use App\Actions\Public\Broadcast\HeartbeatPresenceAction;
use App\Actions\Public\Broadcast\JoinPresenceAction;
use App\Actions\Public\Broadcast\ShowPresenceAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\Broadcast\PresenceHeartbeatRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * محرّك حضور البثّ العام (نموذج HTTP heartbeat — لا WebSockets عامة).
 *
 *   GET  /presence            → قراءة العدّ/الحالة (CDN-safe، تجميعيّ).
 *   POST /presence/join       → إصدار رمز جلسة موقّع + حالة/عدّ أوّليّ.
 *   POST /presence/heartbeat  → نبضة (تسجيل حضور + حالة تحكّم تعاونية).
 *
 * {broadcast} مُعرّف رقمي (لا ربط نموذج) — تفادي ضرب قاعدة البيانات على المسار الساخن؛
 * اللقطة المُخزّنة (BroadcastPresence::snapshot) تتولّى البحث والتحقّق من الرؤية.
 */
class PresenceController extends Controller
{
    public function show(string $broadcast): JsonResponse
    {
        return (new ShowPresenceAction)->handle((int) $broadcast);
    }

    public function join(Request $request, string $broadcast): JsonResponse
    {
        return (new JoinPresenceAction)->handle((int) $broadcast, $request);
    }

    public function heartbeat(PresenceHeartbeatRequest $request, string $broadcast): JsonResponse
    {
        return (new HeartbeatPresenceAction)->handle(
            (int) $broadcast,
            (string) $request->validated('token'),
            $request,
        );
    }
}
