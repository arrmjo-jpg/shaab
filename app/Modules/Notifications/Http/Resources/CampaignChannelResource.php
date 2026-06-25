<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Http\Resources;

use App\Modules\Notifications\Models\NotificationCampaignChannel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin NotificationCampaignChannel
 */
final class CampaignChannelResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'channel' => $this->channel->value,
            'status' => $this->status->value,
            'mode' => $this->mode?->value,
            'addressing' => $this->addressing?->value,
            'tracking_mode' => $this->tracking_mode?->value,
            'channel_priority' => $this->channel_priority,
            'fallback_channel' => $this->fallback_channel,
            'template_id' => $this->template_id,
            'skip_reason' => $this->skip_reason,
            'topic' => $this->topic,
            'content' => $this->content, // snapshot المُصيَّر (immutable)
            'counters' => [
                'targeted' => (int) $this->targeted,
                'sent' => (int) $this->sent,
                'delivered' => (int) $this->delivered,
                'failed' => (int) $this->failed,
                'skipped' => (int) $this->skipped,
                'invalid' => (int) $this->invalid,
            ],
        ];
    }
}
