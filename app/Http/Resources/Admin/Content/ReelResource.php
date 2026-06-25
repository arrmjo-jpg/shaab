<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Content;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * مورد الريل (لوحة الإدارة) — مخرَج deterministic، لا نماذج خام.
 * تفاصيل الوسائط تُثرى في المرحلة 3، ومقاييس التفاعل في المرحلة 4.
 */
class ReelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'status' => $this->status->value,
            'is_featured' => $this->is_featured,
            'locale' => $this->locale,
            'translation_group' => $this->translation_group,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'duration_seconds' => $this->duration_seconds,
            'sort_order' => $this->sort_order,
            'seo' => [
                'title' => $this->seo_title,
                'description' => $this->seo_description,
                'keywords' => $this->seo_keywords,
                'canonical_url' => $this->canonical_url,
                'robots' => $this->robots,
            ],
            // مشاركة: نفس بدائيّات المقال (مسار قانوني + صورة OG من الوسائط).
            'canonical_path' => $this->canonicalPath(),
            'share_image' => $this->whenLoaded('mediaAsset', fn (): ?string => $this->shareImageUrl()),
            // مقاييس التفاعل الموحّدة (عند تحميل العدّاد مسبقاً — لا N+1).
            'metrics' => $this->whenLoaded('engagementCounter', fn (): array => $this->engagementMetrics()),
            'media_asset_id' => $this->media_asset_id,
            'media' => $this->whenLoaded('mediaAsset', fn (): ?array => $this->mediaAsset === null ? null : [
                'id' => $this->mediaAsset->id,
                'uuid' => $this->mediaAsset->uuid,
                'processing_status' => $this->mediaAsset->processing_status,
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
