<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Http\Resources;

use App\Modules\Notifications\Models\NotificationTemplate;
use App\Modules\Notifications\Support\EventCatalog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin NotificationTemplate
 */
final class TemplateResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_key' => $this->event_key,
            'event_label' => EventCatalog::get($this->event_key)['label'] ?? $this->event_key,
            'channel' => $this->channel->value,
            'locale' => $this->locale,
            'title' => $this->title,
            'body' => $this->body,
            'image_strategy' => $this->image_strategy,
            'deep_link_type' => $this->deep_link_type,
            'deep_link_value' => $this->deep_link_value,
            'is_default' => (bool) $this->is_default,
            'available_variables' => EventCatalog::variablesFor($this->event_key),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
