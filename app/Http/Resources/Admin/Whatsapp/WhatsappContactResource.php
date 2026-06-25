<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Whatsapp;

use App\Models\WhatsappContact;
use App\Models\WhatsappGroup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property WhatsappContact $resource */
class WhatsappContactResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'phone' => $this->resource->phone,
            'status' => $this->resource->status->value,
            'source' => $this->resource->source,
            'groups' => $this->resource->relationLoaded('groups')
                ? $this->resource->groups->map(fn (WhatsappGroup $g): array => ['id' => $g->id, 'name' => $g->name])->all()
                : [],
            'created_at' => $this->resource->created_at?->toIso8601String(),
        ];
    }
}
