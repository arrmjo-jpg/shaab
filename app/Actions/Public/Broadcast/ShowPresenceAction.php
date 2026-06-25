<?php

declare(strict_types=1);

namespace App\Actions\Public\Broadcast;

use App\Support\Broadcast\BroadcastPresence;
use App\Support\Broadcast\BroadcastPresenceControl;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * قراءة العدّ/الحالة العامة (CDN-safe) — تجميعيّ فقط: لا رمز، لا هوية، لا تسجيل.
 * ترويسة كاش عامة قصيرة (count_cache_ttl) ⇒ الحافة تمتصّ القراءات الجماهيرية بينما
 * يحسب الأصل مرّة واحدة كل نافذة (تحمّل 100k دون إغراق الأصل). غير مرئي عموماً ⇒ 404.
 */
class ShowPresenceAction
{
    public function handle(int $broadcastId): JsonResponse
    {
        $snapshot = BroadcastPresence::snapshot($broadcastId);
        if ($snapshot === null
            || ! BroadcastPresenceControl::isPubliclyVisible($snapshot['status'], $snapshot['is_public'])) {
            return ApiResponse::error(__('broadcast.not_found'), [], 404);
        }

        $ttl = max(1, (int) config('broadcast.presence.count_cache_ttl', 15));

        return ApiResponse::success(data: [
            'status' => $snapshot['status'],
            'viewers_now' => BroadcastPresence::viewersNow($snapshot['status'], $broadcastId),
        ])->header('Cache-Control', "public, max-age={$ttl}, s-maxage={$ttl}");
    }
}
