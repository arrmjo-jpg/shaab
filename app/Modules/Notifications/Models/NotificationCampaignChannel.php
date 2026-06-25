<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Models;

use App\Modules\Notifications\Enums\AddressingModel;
use App\Modules\Notifications\Enums\CampaignChannelStatus;
use App\Modules\Notifications\Enums\ChannelKey;
use App\Modules\Notifications\Enums\DeliveryMode;
use App\Modules\Notifications\Enums\TrackingMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * قناة حملة — snapshot ثابت من لحظة الإنشاء (channel_priority/mode/addressing/tracking_mode من
 * المصفوفة، لا قراءة حيّة). **غير مُدقَّقة** عمداً: العدّادات تتغيّر بكثرة (نمط WhatsappCampaignMessage).
 * tracking_mode مشتقّ من addressing (topic⇒aggregate · per_recipient⇒per_recipient).
 */
class NotificationCampaignChannel extends Model
{
    protected $table = 'notification_campaign_channels';

    protected $fillable = [
        'campaign_id', 'channel', 'mode', 'tracking_mode', 'status', 'skip_reason',
        'addressing', 'channel_priority', 'fallback_channel', 'template_id', 'content',
        'fallback_from', 'topic', 'provider_ref',
        'targeted', 'sent', 'delivered', 'failed', 'skipped', 'invalid', 'opened', 'clicked',
    ];

    protected function casts(): array
    {
        return [
            'channel' => ChannelKey::class,
            'mode' => DeliveryMode::class,
            'tracking_mode' => TrackingMode::class,
            'status' => CampaignChannelStatus::class,
            'addressing' => AddressingModel::class,
            'channel_priority' => 'integer',
            'template_id' => 'integer',
            'content' => 'array',
            'targeted' => 'integer',
            'sent' => 'integer',
            'delivered' => 'integer',
            'failed' => 'integer',
            'skipped' => 'integer',
            'invalid' => 'integer',
            'opened' => 'integer',
            'clicked' => 'integer',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(NotificationCampaign::class, 'campaign_id');
    }
}
