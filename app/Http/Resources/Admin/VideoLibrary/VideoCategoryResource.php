<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\VideoLibrary;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * مورد تصنيف الفيديو (لوحة الإدارة). الأبناء يُضمَّنون عند بناء الشجرة (children
 * مُحمَّلة)؛ عدّاد الفيديوهات عند طلبه.
 */
class VideoCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'locale' => $this->locale,
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
            'videos_count' => $this->whenCounted('videos'),
            'children' => self::collection($this->whenLoaded('children')),
            'deleted_at' => $this->deleted_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
