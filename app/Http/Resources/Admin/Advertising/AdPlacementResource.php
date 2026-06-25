<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Advertising;

use App\Models\AdPlacement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AdPlacement
 */
class AdPlacementResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ad_creative_id' => $this->ad_creative_id,
            'ad_zone_id' => $this->ad_zone_id,
            'weight' => $this->weight,
            // الوزن الفعّال: وزن الإسناد إن وُجد، وإلا وزن الإبداع، وإلا 1.
            'effective_weight' => $this->effectiveWeight(),
            'is_active' => $this->is_active,
            'device_targets' => $this->device_targets,
            'creative' => $this->whenLoaded('creative', fn (): array => [
                'id' => $this->creative->id,
                'title' => $this->creative->title,
                'type' => $this->creative->type?->value,
            ]),
            'zone' => $this->whenLoaded('zone', fn (): array => [
                'id' => $this->zone->id,
                'key' => $this->zone->key,
                'name' => $this->zone->name,
                'placement_type' => $this->zone->placement_type?->value,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
