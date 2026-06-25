<?php

declare(strict_types=1);

namespace App\Http\Resources\Public\VideoLibrary;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * بطاقة فيديو عامة خفيفة — لِلقوائم/الخلاصات/الأعضاء (الجوّال أولاً). تحمل ما يلزم
 * للعرض والتشغيل دون كتلة SEO الكاملة (لا canonical/hreflang/JSON-LD لكل عنصر):
 *
 *   - يقصّ حمولة القائمة بشكل ملموس (لا تكرار VideoObject لكل بطاقة).
 *   - يلغي N+1 الخاص بـ hreflang (كان VideoSeoBuilder يستعلم الأشقّاء لكل عنصر).
 *
 * SEO الكامل يبقى حصراً على نقطة التفاصيل (PublicVideoResource) — وهي صفحة الزحف
 * الفعلية. الزواحف تفهرس التفاصيل لا استجابات القوائم.
 */
class PublicVideoCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'locale' => $this->locale,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'excerpt' => $this->excerpt,
            'duration_seconds' => $this->duration_seconds,
            'source_type' => $this->source_type,
            'is_featured' => $this->is_featured,
            'published_at' => $this->published_at?->toISOString(),
            'canonical_path' => $this->canonicalPath(),
            'share_image' => $this->shareImageUrl(),
            'category' => $this->whenLoaded('category', fn () => $this->category === null ? null : [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
            ]),
            'metrics' => $this->engagementMetrics(),
            'media' => $this->mediaPayload(),
        ];
    }

    /** روابط التشغيل: الخارجي يُضمَّن (embed)، المرفوع يُشغَّل (HLS + نسخ MP4). */
    protected function mediaPayload(): ?array
    {
        $media = $this->mediaAsset;
        if ($media === null) {
            return null;
        }

        if ($media->isExternal()) {
            return [
                'kind' => 'external',
                'provider' => $media->provider,
                'embed_url' => $media->embed_url,
                'source_url' => $media->source_url,
                'poster' => $media->posterUrl(),
            ];
        }

        return [
            'kind' => 'uploaded',
            'poster' => $media->posterUrl(),
            'hls' => $media->hlsUrl(),
            'renditions' => $media->renditionUrls(),
            'processing_status' => $media->processing_status,
        ];
    }
}
