<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Http\Resources;

use App\Modules\Notifications\Models\NotificationEventChannel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * صفّ مصفوفة (event × channel) — إعداد القناة لحدثٍ ما. تقرؤه الواجهة لعرض/تحرير المصفوفة.
 *
 * @mixin NotificationEventChannel
 */
final class EventChannelResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'channel' => $this->channel->value,
            'mode' => $this->mode->value,
            'channel_priority' => $this->channel_priority,
            'fallback_channel' => $this->fallback_channel,
            'template_id' => $this->template_id,
            'default_audience_id' => $this->default_audience_id,
        ];
    }
}
