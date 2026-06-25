<?php

declare(strict_types=1);

namespace App\Actions\Admin\Advertising;

use App\Enums\AdCampaignStatus;
use App\Models\AdCampaign;
use App\Models\User;
use App\Support\Advertising\AdCampaignLifecycle;
use App\Support\Advertising\AdServingInvalidator;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * انتقال يدويّ لحالة الحملة — يفرض آلة الحالة (AdCampaignLifecycle) صراحةً + حارس
 * النافذة عند التفعيل، ثم يُبطل بِرَك الخدمة المتأثّرة. الانتقالات غير المسموحة تُعيد
 * ApiResponse (لا استثناءات — سياسة AlphaCMS). التفويض (ads.publish) يُفرَض في الـ Policy.
 */
class ChangeAdCampaignStatusAction
{
    public function handle(AdCampaign $campaign, AdCampaignStatus $to, User $actor): JsonResponse
    {
        $from = $campaign->status;

        if ($from === $to || ! AdCampaignLifecycle::canTransitionManually($from, $to)) {
            return ApiResponse::error(__('ads.campaign.invalid_transition'), [], 422);
        }

        // حارس النشر الوحيد: لا نشر/تفعيل قبل اكتمال الشروط (publishValidation مصدر الحقيقة،
        // Result Object بسبب محدَّد + مفتاح رسالة + تفاصيل اختياريّة).
        if (in_array($to, [AdCampaignStatus::Scheduled, AdCampaignStatus::Active], true)) {
            $validation = $campaign->publishValidation();
            if (! $validation->ok) {
                return ApiResponse::error(__((string) $validation->messageKey), $validation->details, 422);
            }
        }

        if ($to === AdCampaignStatus::Active && ! AdCampaignLifecycle::canActivateNow($campaign)) {
            return ApiResponse::error(__('ads.campaign.window_expired'), [], 422);
        }

        DB::transaction(function () use ($campaign, $to, $actor): void {
            $campaign->status = $to;
            $campaign->updated_by = $actor->id;
            $campaign->save();
        });

        // إبطال صريح لبِرَك الخدمة المتأثّرة (لا observer).
        AdServingInvalidator::forCampaign($campaign);

        return ApiResponse::success(__('ads.campaign.status_changed'), [
            'id' => $campaign->id,
            'status' => $campaign->status->value,
        ]);
    }
}
