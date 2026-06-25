<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Actions;

use App\Modules\Notifications\Enums\CampaignStatus;
use App\Modules\Notifications\Models\NotificationCampaign;
use App\Modules\Notifications\Support\CampaignTransitionException;

/**
 * يوقف حملة **قبل الإرسال** (Scheduled/Queued → Paused) فيمنع إطلاقها. لإيقاف حملة جارية
 * (Sending) استُعمل الإلغاء (لا يمكن سحب دفعات مُجدوَلة بأمان). ذرّيّ عبر UPDATE…WHERE status=الحالي.
 */
final class PauseCampaignAction
{
    public function handle(NotificationCampaign $campaign): NotificationCampaign
    {
        $from = $campaign->status;

        if (! in_array($from, [CampaignStatus::Scheduled, CampaignStatus::Queued], true)) {
            throw new CampaignTransitionException('لا يمكن إيقاف إلّا حملة مجدولة أو في الطابور. لإيقاف حملة جارية استعمل الإلغاء.');
        }

        $claimed = NotificationCampaign::query()
            ->where('id', $campaign->id)
            ->where('status', $from->value)
            ->update(['status' => CampaignStatus::Paused->value]);

        if ($claimed === 0) {
            throw new CampaignTransitionException('تغيّرت حالة الحملة بالتزامن.');
        }

        return $campaign->refresh();
    }
}
