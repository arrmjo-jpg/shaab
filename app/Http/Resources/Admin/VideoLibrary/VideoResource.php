<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\VideoLibrary;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * مورد فيديو المكتبة (لوحة الإدارة) — مخرَج deterministic، لا نماذج خام.
 */
class VideoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'status' => $this->status->value,
            'visibility' => $this->visibility->value,
            'source_type' => $this->source_type,
            'is_featured' => $this->is_featured,
            'locale' => $this->locale,
            'translation_group' => $this->translation_group,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'excerpt' => $this->excerpt,
            'duration_seconds' => $this->duration_seconds,
            'views_count' => $this->views_count,
            'sort_order' => $this->sort_order,
            'seo' => [
                'title' => $this->seo_title,
                'description' => $this->seo_description,
                'keywords' => $this->seo_keywords,
                'canonical_url' => $this->canonical_url,
                'robots' => $this->robots,
            ],
            'canonical_path' => $this->canonicalPath(),
            'share_image' => $this->whenLoaded('mediaAsset', fn (): ?string => $this->shareImageUrl()),
            'metrics' => $this->whenLoaded('engagementCounter', fn (): array => $this->engagementMetrics()),
            'category' => $this->whenLoaded('category', fn () => $this->category === null ? null : [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
            ]),
            'video_category_id' => $this->video_category_id,
            'media_asset_id' => $this->media_asset_id,
            'media' => $this->whenLoaded('mediaAsset', fn (): ?array => $this->mediaAsset === null ? null : [
                'id' => $this->mediaAsset->id,
                'uuid' => $this->mediaAsset->uuid,
                'kind' => $this->mediaAsset->kind,
                'provider' => $this->mediaAsset->provider,
                'processing_status' => $this->mediaAsset->processing_status,
                'embed_url' => $this->mediaAsset->embed_url,
                'poster_url' => $this->mediaAsset->posterUrl(),
            ]),
            'author' => $this->whenLoaded('author', fn () => [
                'id' => $this->author?->id,
                'name' => $this->author?->name,
            ]),
            'published_at' => $this->published_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
