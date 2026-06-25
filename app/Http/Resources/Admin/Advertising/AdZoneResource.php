<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Advertising;

use App\Models\AdZone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AdZone
 */
class AdZoneResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'name' => $this->name,
            'description' => $this->description,
            'placement_type' => $this->placement_type?->value,
            'selector_strategy' => $this->selector_strategy?->value,
            'width' => $this->width,
            'height' => $this->height,
            'locale' => $this->locale,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'placements_count' => $this->whenCounted('placements'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
