<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Http\Resources;

use App\Modules\Notifications\Models\NotificationEventType;
use App\Modules\Notifications\Support\EventCatalog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * حدث + مصفوفة قنواته للإدارة. label/variables من الكتالوج (SoT)؛ enabled قابل للتبديل من الأدمن.
 *
 * @mixin NotificationEventType
 */
final class EventMatrixResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'label' => EventCatalog::get($this->key)['label'] ?? $this->key,
            'category' => $this->category,
            'source' => $this->source,
            'default_priority' => $this->default_priority,
            'enabled' => (bool) $this->enabled,
            'archived' => (bool) $this->archived,
            'user_visible' => (bool) $this->is_user_visible,
            'manual_dispatch' => (bool) $this->supports_manual_dispatch,
            'variables' => EventCatalog::variablesFor($this->key),
            'channels' => EventChannelResource::collection($this->whenLoaded('channels')),
        ];
    }
}
