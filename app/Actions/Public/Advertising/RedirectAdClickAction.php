<?php

declare(strict_types=1);

namespace App\Actions\Public\Advertising;

use App\Enums\AdEventType;
use App\Enums\TrafficChannel;
use App\Models\AdPlacement;
use App\Support\Advertising\AdBeaconToken;
use App\Support\Advertising\AdClientIp;
use App\Support\Advertising\AdTracker;
use App\Support\Advertising\AdUrlSafety;
use App\Support\Engagement\EngagementActor;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * تحويل النقرة الموقّع (GET /ads/click/{token}). الرمز وحده يحمل (placement, zone,
 * bucket)؛ يُفكّ ويُتحقَّق منه. الوجهة هي landing_url المُخزَّن للإبداع بعد إعادة التحقّق
 * (AdUrlSafety) — لا يُعاد توجيه أبداً لرابط يمرّره العميل (لا open redirect). تُسجَّل
 * النقرة (تصفية بوت + منع تكرار داخل AdTracker) ثم تحويل 302. لا تُخزَّن (no-store).
 */
final class RedirectAdClickAction
{
    public function handle(string $token, Request $request): Response
    {
        $decoded = AdBeaconToken::verifyAndDecode($token);
        if ($decoded === null) {
            return ApiResponse::error(__('ads.tracking.invalid_token'), [], 422);
        }

        $placement = AdPlacement::query()
            ->whereKey($decoded['placement_id'])
            ->with('creative:id,landing_url')
            ->first(['id', 'ad_creative_id']);

        $target = AdUrlSafety::safeTarget($placement?->creative?->landing_url);
        if ($target === null) {
            return ApiResponse::error(__('ads.tracking.click_unavailable'), [], 404);
        }

        AdTracker::record(
            AdEventType::Click,
            $decoded['placement_id'],
            EngagementActor::fromRequest($request),
            TrafficChannel::fromRequest($request)->value,
            $decoded['bucket'],
            AdClientIp::key($request),
        );

        return redirect()->away($target, 302)
            ->header('Cache-Control', 'no-store, max-age=0');
    }
}
