<?php

declare(strict_types=1);

namespace App\Support\Broadcast;

use App\Enums\BroadcastStatus;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * حارس انتقالات دورة حياة البثّ — مرآة VideoCategoryHierarchyGuard (يُرجِع JsonResponse
 * عند الرفض أو null عند السلامة؛ لا استثناءات أعمال). آلة الحالة مصدرها BroadcastStatus.
 */
final class BroadcastTransitionGuard
{
    public static function check(BroadcastStatus $from, BroadcastStatus $to): ?JsonResponse
    {
        if (! $from->canTransitionTo($to)) {
            return ApiResponse::error(
                __('broadcast.transition.forbidden', ['from' => $from->value, 'to' => $to->value]),
                [],
                422,
            );
        }

        return null;
    }
}
