<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Advertising;

use App\Models\AdCampaign;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AdCampaign
 */
class AdCampaignResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'advertiser_name' => $this->advertiser_name,
            'status' => $this->status?->value,
            'priority' => $this->priority,
            'weight' => $this->weight,
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            // جاهز-مستقبلاً (لا محرّك الآن).
            'budget_total' => $this->budget_total,
            'budget_spent' => $this->budget_spent,
            'pacing_mode' => $this->pacing_mode?->value,
            'targeting' => $this->targeting,
            'creatives_count' => $this->whenCounted('creatives'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
