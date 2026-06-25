<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Broadcast;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * مورد تصنيف البثّ (لوحة الإدارة) — مسطّح. عدّاد البثّ عند طلبه؛ الغلاف عند تحميله.
 */
class BroadcastCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'cover_media_id' => $this->cover_media_id,
            'cover_url' => $this->whenLoaded('cover', fn (): ?string => $this->cover?->posterUrl() ?? $this->cover?->url()),
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'seo' => [
                'title' => $this->seo_title,
                'description' => $this->seo_description,
            ],
            'broadcasts_count' => $this->whenCounted('broadcasts'),
            'deleted_at' => $this->deleted_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
