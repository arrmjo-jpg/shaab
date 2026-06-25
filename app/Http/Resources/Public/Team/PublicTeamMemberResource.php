<?php

declare(strict_types=1);

namespace App\Http\Resources\Public\Team;

use App\Support\Content\TeamMemberSeoBuilder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * مورد عضو الفريق للقراءة العامة (تفاصيل) — حقول مُعقَّمة + حمولة SEO كاملة
 * (Person JSON-LD عبر TeamMemberSeoBuilder). bio_html مُنقّى مسبقاً عند الكتابة.
 * الصورة من المكتبة المركزية (avatarAsset — يجب تحميله مسبقاً).
 */
class PublicTeamMemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'job_title' => $this->job_title,
            'department' => $this->department,
            'slug' => $this->slug,
            'bio_html' => $this->bio,
            'avatar' => $this->avatarAsset ? [
                'url' => $this->avatarAsset->url(),
                'thumb' => $this->avatarAsset->conversionUrl('thumb'),
                'medium' => $this->avatarAsset->conversionUrl('medium'),
                'width' => $this->avatarAsset->width,
                'height' => $this->avatarAsset->height,
            ] : null,
            'social_links' => (object) ($this->social_links ?? []),
            'canonical_path' => $this->canonicalPath(),
            'seo' => TeamMemberSeoBuilder::build($this->resource),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
