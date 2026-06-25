<?php

declare(strict_types=1);

namespace App\Actions\Admin\Advertising;

use App\Enums\AdCampaignStatus;
use App\Http\Resources\Admin\Advertising\AdCampaignResource;
use App\Models\AdCampaign;
use App\Models\User;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * إنشاء حملة — تُنشأ دائماً **draft** (آمن: لا نشر بالخطأ؛ الحملة الجديدة بلا إبداع/إسناد فلا
 * يمكن أن تكون مكتملة بعد). النشر = انتقال محروس draft→scheduled (ChangeAdCampaignStatusAction)
 * بعد إضافة إبداع وربطه بمساحة. نسبة الإنشاء/التعديل تُسجَّل. الحملة وحدها بلا إسناد لا تُخدَم
 * ⇒ لا بِركة خدمة لإبطالها هنا.
 */
class CreateAdCampaignAction
{
    /** @param  array<string, mixed>  $data */
    public function handle(array $data, User $actor): JsonResponse
    {
        $data['status'] = AdCampaignStatus::Draft->value;
        $data['created_by'] = $actor->id;
        $data['updated_by'] = $actor->id;

        $campaign = AdCampaign::create($data);

        // fresh(): يضمن تحميل قيم الأعمدة الافتراضية (uuid/budget_spent/pacing_mode…).
        return ApiResponse::success(__('ads.campaign.created'), new AdCampaignResource($campaign->fresh()), 201);
    }
}
