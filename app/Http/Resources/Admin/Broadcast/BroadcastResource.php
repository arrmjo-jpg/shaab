<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Broadcast;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * مورد البثّ (لوحة الإدارة) — مخرَج deterministic، لا نماذج خام. الـ enums تُسلسَل
 * بقيمتها النصّية. عدّاد المشاهدين لقطة (snapshot). صحّة آخر فحص مُجمَّعة في كائن.
 */
class BroadcastResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'status' => $this->status->value,
            'kind' => $this->kind->value,
            'source_type' => $this->source_type->value,
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'description' => $this->description,
            'source_url' => $this->source_url,
            'category_id' => $this->category_id,
            'category' => $this->whenLoaded('category', fn () => $this->category === null ? null : [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
            ]),
            'vod_video_id' => $this->vod_video_id,
            'vod' => $this->whenLoaded('vodVideo', fn () => $this->vodVideo === null ? null : [
                'id' => $this->vodVideo->id,
                'title' => $this->vodVideo->title,
                'slug' => $this->vodVideo->slug,
            ]),
            'thumbnail_path' => $this->thumbnail_path,
            'poster_path' => $this->poster_path,
            'cover_media_id' => $this->cover_media_id,
            'cover_url' => $this->whenLoaded('cover', fn (): ?string => $this->cover?->posterUrl() ?? $this->cover?->url()),
            'seo' => [
                'title' => $this->seo_title,
                'description' => $this->seo_description,
                'keywords' => $this->seo_keywords,
                'canonical_url' => $this->canonical_url,
                'robots' => $this->robots,
            ],
            'scheduled_at' => $this->scheduled_at?->toISOString(),
            'started_at' => $this->started_at?->toISOString(),
            'ended_at' => $this->ended_at?->toISOString(),
            'health' => [
                'status' => $this->last_health_status,
                'checked_at' => $this->last_health_check_at?->toISOString(),
                'message' => $this->last_health_message,
            ],
            'viewer_count' => $this->viewer_count,
            'sort_order' => $this->sort_order,
            'is_featured' => $this->is_featured,
            'is_public' => $this->is_public,
            'meta' => $this->meta,
            'creator' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator?->id,
                'name' => $this->creator?->name,
            ]),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
        ];
    }
}
