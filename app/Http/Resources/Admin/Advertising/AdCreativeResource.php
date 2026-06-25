<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Advertising;

use App\Models\AdCreative;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AdCreative
 */
class AdCreativeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'ad_campaign_id' => $this->ad_campaign_id,
            'type' => $this->type?->value,
            'title' => $this->title,
            'alt_text' => $this->alt_text,
            'landing_url' => $this->landing_url,
            // html_code مُنقّى مسبقاً عند الكتابة (HTMLPurifier).
            'html_code' => $this->html_code,
            'media_asset_id' => $this->media_asset_id,
            'media' => $this->whenLoaded('mediaAsset', fn (): ?array => $this->mediaAsset === null ? null : [
                'id' => $this->mediaAsset->id,
                'url' => $this->mediaAsset->url(),
            ]),
            'weight' => $this->weight,
            'is_active' => $this->is_active,
            'campaign' => $this->whenLoaded('campaign', fn (): array => [
                'id' => $this->campaign->id,
                'name' => $this->campaign->name,
                'status' => $this->campaign->status?->value,
            ]),
            'placements_count' => $this->whenCounted('placements'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
