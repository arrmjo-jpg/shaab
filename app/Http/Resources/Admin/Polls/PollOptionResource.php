<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Polls;

use App\Models\PollOption;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PollOption
 */
class PollOptionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'sort_order' => $this->sort_order,
            'votes_count' => (int) $this->votes_count,
        ];
    }
}
