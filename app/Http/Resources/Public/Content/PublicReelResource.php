<?php

declare(strict_types=1);

namespace App\Http\Resources\Public\Content;

use App\Support\Content\ReelSeoBuilder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * مورد الريل العام — حقول مُعقَّمة فقط (لا حالة/مؤلّف خام/أعلام إدارية).
 * كل ما يُعاد منشور. يُعيد استخدام canonicalPath + صورة المشاركة + المقاييس
 * الموحّدة + روابط الوسائط القائمة (HLS + نسخ MP4 + poster).
 */
class PublicReelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'locale' => $this->locale,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'duration_seconds' => $this->duration_seconds,
            'is_featured' => $this->is_featured,
            'published_at' => $this->published_at?->toISOString(),
            'canonical_path' => $this->canonicalPath(),
            'share_image' => $this->shareImageUrl(),
            // SEO كامل: canonical + hreflang/x-default + OG/Twitter + VideoObject.
            'seo' => ReelSeoBuilder::build($this->resource),
            // مقاييس التفاعل الموحّدة (يُحمَّل العدّاد مسبقاً في الـ Action — لا N+1).
            'metrics' => $this->engagementMetrics(),
            'media' => $this->mediaPayload(),
        ];
    }

    /** روابط التشغيل من المكتبة المركزية (HLS + نسخ MP4 + poster). */
    private function mediaPayload(): ?array
    {
        $media = $this->mediaAsset;
        if ($media === null) {
            return null;
        }

        return [
            'poster' => $media->posterUrl(),
            'hls' => $media->hlsUrl(),
            'renditions' => $media->renditionUrls(),
            'processing_status' => $media->processing_status,
        ];
    }
}
