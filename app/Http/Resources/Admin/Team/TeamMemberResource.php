<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Team;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * مورد عضو الفريق (لوحة الإدارة) — مخرَج deterministic، لا نماذج خام.
 */
class TeamMemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'job_title' => $this->job_title,
            'department' => $this->department,
            'status' => $this->status->value,
            'slug' => $this->slug,
            'bio_html' => $this->bio,
            'avatar_asset_id' => $this->avatar_asset_id,
            // كائن روابط مشتقّ من MediaAsset (CDN + conversions) — null-safe. يُحمَّل
            // مسبقاً دائماً (avatarAsset) لمنع N+1. القائمة تستخدم thumb لا الأصل.
            'avatar' => $this->avatarAsset ? [
                'id' => $this->avatarAsset->id,
                'url' => $this->avatarAsset->url(),
                'thumb' => $this->avatarAsset->conversionUrl('thumb'),
                'medium' => $this->avatarAsset->conversionUrl('medium'),
                'width' => $this->avatarAsset->width,
                'height' => $this->avatarAsset->height,
            ] : null,
            'social_links' => (object) ($this->social_links ?? []),
            'seo' => [
                'title' => $this->seo_title,
                'description' => $this->seo_description,
                'keywords' => $this->seo_keywords,
                'canonical_url' => $this->canonical_url,
                'robots' => $this->robots,
            ],
            'canonical_path' => $this->canonicalPath(),
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
        ];
    }
}
