<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Polls;

use App\Models\Poll;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Poll
 */
class PollResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'question' => $this->question,
            'allow_multiple' => (bool) $this->allow_multiple,
            'is_active' => (bool) $this->is_active,
            'state' => $this->state(),
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'audience_mode' => $this->audience_mode?->value,
            'result_visibility' => $this->result_visibility?->value,
            'options_count' => $this->whenCounted('options'),
            'options' => PollOptionResource::collection($this->whenLoaded('options')),
            'total_votes' => $this->when(
                $this->relationLoaded('options'),
                fn (): int => (int) $this->options->sum('votes_count'),
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
