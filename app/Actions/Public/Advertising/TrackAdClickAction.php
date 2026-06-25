<?php

declare(strict_types=1);

namespace App\Actions\Public\Advertising;

use App\Enums\AdEventType;
use App\Enums\TrafficChannel;
use App\Support\Advertising\AdBeaconToken;
use App\Support\Advertising\AdClientIp;
use App\Support\Advertising\AdTracker;
use App\Support\Engagement\EngagementActor;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * منارة نقرة العميل (POST /ads/track/click) — لإبداعات HTML التي تملك روابطها الخاصّة،
 * فلا تمرّ بتحويل النقرة الموقّع (إبداعات الصورة تبقى على 302). الرمز يحمل (placement,
 * zone, bucket) ويُفكّ ويُتحقَّق منه؛ التصفية (بوت) ومنع التكرار داخل AdTracker. نُعيد
 * accepted دائماً عند رمز صالح (لا تسريب لحالة الاحتساب). لا تُخزَّن (no-store).
 */
final class TrackAdClickAction
{
    public function handle(string $token, Request $request): JsonResponse
    {
        $decoded = AdBeaconToken::verifyAndDecode($token);
        if ($decoded === null) {
            return ApiResponse::error(__('ads.tracking.invalid_token'), [], 422)
                ->header('Cache-Control', 'no-store, max-age=0');
        }

        AdTracker::record(
            AdEventType::Click,
            $decoded['placement_id'],
            EngagementActor::fromRequest($request),
            TrafficChannel::fromRequest($request)->value,
            $decoded['bucket'],
            AdClientIp::key($request),
        );

        return ApiResponse::success(__('ads.tracking.accepted'), ['accepted' => true])
            ->header('Cache-Control', 'no-store, max-age=0');
    }
}
