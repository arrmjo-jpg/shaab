<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Whatsapp;

use App\Models\WhatsappGroup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property WhatsappGroup $resource */
class WhatsappGroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'description' => $this->resource->description,
            'is_default' => $this->resource->is_default,
            'contacts_count' => (int) ($this->resource->contacts_count ?? 0),
            'created_at' => $this->resource->created_at?->toIso8601String(),
        ];
    }
}
