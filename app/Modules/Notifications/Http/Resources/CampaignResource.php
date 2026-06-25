<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Http\Resources;

use App\Modules\Notifications\Models\NotificationCampaign;
use App\Modules\Notifications\Support\EventCatalog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * تمثيل حملة للإدارة — يضمّ حالة + **allowed_transitions** (لتمكين أزرار الواجهة)، إحصاءات
 * مجمَّعة من القنوات (channels_sum_* عند توفّرها)، وتفصيل القنوات عند تحميلها.
 *
 * @mixin NotificationCampaign
 */
final class CampaignResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'event_key' => $this->event_key,
            'event_label' => EventCatalog::get($this->event_key)['label'] ?? $this->event_key,
            'source' => $this->source?->value,
            'trigger_type' => $this->trigger_type?->value,
            'priority' => $this->priority?->value,
            'title' => $this->title,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'is_terminal' => $this->status->isTerminal(),
            'allowed_transitions' => array_map(
                static fn ($s): string => $s->value,
                $this->status->allowedTransitions(),
            ),
            'audience' => $this->audience_spec,
            'scheduled_at' => $this->scheduled_at,
            'started_at' => $this->started_at,
            'finished_at' => $this->finished_at,
            'stats' => [
                'channels' => (int) ($this->channels_count ?? 0),
                'targeted' => (int) ($this->channels_sum_targeted ?? 0),
                'sent' => (int) ($this->channels_sum_sent ?? 0),
                'failed' => (int) ($this->channels_sum_failed ?? 0),
                'skipped' => (int) ($this->channels_sum_skipped ?? 0),
                'invalid' => (int) ($this->channels_sum_invalid ?? 0),
            ],
            'channels' => CampaignChannelResource::collection($this->whenLoaded('channels')),
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
