<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Models;

use App\Modules\Notifications\Enums\ChannelKey;
use App\Modules\Notifications\Enums\DeliveryStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * سجلّ تسليم لكلّ مُستلِم — **للقنوات per_recipient فقط** (topic لا صفوف). **غير مُدقَّق** (حجم عالٍ).
 * unique(campaign_channel_id, recipient_id) للـidempotency. يُنشَأ في الـjobs لا الـDispatcher.
 */
class NotificationDelivery extends Model
{
    protected $table = 'notification_deliveries';

    protected $fillable = [
        'campaign_channel_id', 'campaign_id', 'channel', 'recipient_type', 'recipient_id',
        'address_snapshot', 'status', 'provider_message_id', 'error', 'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'channel' => ChannelKey::class,
            'status' => DeliveryStatus::class,
            'sent_at' => 'datetime',
        ];
    }

    public function campaignChannel(): BelongsTo
    {
        return $this->belongsTo(NotificationCampaignChannel::class, 'campaign_channel_id');
    }
}
