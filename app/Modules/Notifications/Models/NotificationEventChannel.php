<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Models;

use App\Modules\Notifications\Enums\ChannelKey;
use App\Modules\Notifications\Enums\DeliveryMode;
use App\Support\Audit\AuditsChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * مصفوفة (event × channel) — إعداد القناة لكلّ حدث (mode/priority/fallback/template/audience).
 * يقرؤها CampaignDispatcher ليبني snapshot قنوات الحملة. مُدقَّقة (إعداد، تغيّرات ذات معنى).
 */
class NotificationEventChannel extends Model
{
    use AuditsChanges;

    protected $table = 'notification_event_channels';

    protected string $auditLogName = 'notification_event_channel';

    /** @var array<int,string> */
    protected array $auditAttributes = ['event_id', 'channel', 'mode', 'channel_priority', 'fallback_channel'];

    protected $fillable = [
        'event_id', 'channel', 'mode', 'channel_priority', 'fallback_channel',
        'template_id', 'default_audience_id', 'priority_override',
    ];

    protected function casts(): array
    {
        return [
            'channel' => ChannelKey::class,
            'mode' => DeliveryMode::class,
            'channel_priority' => 'integer',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(NotificationEventType::class, 'event_id');
    }
}
