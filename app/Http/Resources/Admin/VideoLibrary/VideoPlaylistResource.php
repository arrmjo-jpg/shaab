<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\VideoLibrary;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * مورد قائمة تشغيل الفيديو (لوحة الإدارة). الفيديوهات مُرتّبة بـ pivot.position
 * عند تحميلها (videos).
 */
class VideoPlaylistResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'status' => $this->status->value,
            'visibility' => $this->visibility->value,
            'is_featured' => $this->is_featured,
            'locale' => $this->locale,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'cover_media_id' => $this->cover_media_id,
            'cover_url' => $this->whenLoaded('cover', fn (): ?string => $this->cover?->posterUrl() ?? $this->cover?->url()),
            'sort_order' => $this->sort_order,
            'seo' => [
                'title' => $this->seo_title,
                'description' => $this->seo_description,
                'keywords' => $this->seo_keywords,
                'canonical_url' => $this->canonical_url,
                'robots' => $this->robots,
            ],
            'canonical_path' => $this->canonicalPath(),
            'videos_count' => $this->whenCounted('videos'),
            'videos' => VideoResource::collection($this->whenLoaded('videos')),
            'author' => $this->whenLoaded('author', fn () => [
                'id' => $this->author?->id,
                'name' => $this->author?->name,
            ]),
            'published_at' => $this->published_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
