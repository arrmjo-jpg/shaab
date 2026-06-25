<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Media;

use App\Support\Media\MediaUsage;
use App\Support\Media\TranscodeProgress;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * مورد أصل المكتبة المركزية (لوحة الإدارة) — يكشف الروابط العامة + بياناتها
 * التحريرية. لا يكشف القرص/المسار الخامّين.
 */
class MediaAssetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isExternal = $this->isExternal();
        $poster = $this->posterUrl();

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'kind' => $this->kind,
            'url' => $this->url(),
            'thumb' => $isExternal ? $poster : $this->conversionUrl('thumb'),
            'medium' => $isExternal ? $poster : $this->conversionUrl('medium'),
            'mime_type' => $this->mime_type,
            'is_image' => $this->isConvertibleImage(),
            'is_external' => $isExternal,
            'is_video' => $isExternal || $this->isUploadedVideo(),
            'provider' => $this->provider,
            'provider_id' => $this->provider_id,
            'embed_url' => $this->embed_url,
            'source_url' => $this->source_url,
            'poster' => $poster,
            // دورة معالجة الفيديو المرفوع
            'processing_status' => $this->processing_status,
            // قائمة تحقّق حبيبية لتقدّم الترميز (فيديو مرفوع فقط — null لغيره)
            'processing' => TranscodeProgress::for($this->resource),
            'duration' => $this->duration_seconds,
            'hls' => $this->hlsUrl(),
            // نسخ MP4 التدريجية + الصورة المصغّرة (ملف معالجة reel — فارغة لغيره)
            'renditions' => $this->renditionUrls(),
            'thumbnail' => $this->thumbnailUrls(),
            'width' => $this->width,
            'height' => $this->height,
            'size' => $this->size,
            'original_name' => $this->original_name,
            'filename' => $this->filename,
            'extension' => $this->extension,
            'checksum' => $this->checksum,
            'alt' => $this->alt,
            'caption' => $this->caption,
            'credit' => $this->credit,
            'source' => $this->source,
            'created_at' => $this->created_at?->toISOString(),
            ...$this->usagePayload(),
        ];
    }

    /**
     * عدّاد الاستخدام (القائمة via withCount) + تفاصيل «أين يُستخدَم» (التفصيل
     * via eager-loaded relations). يُضاف فقط عند توفّره — لا يثقّل سياقات أخرى.
     *
     * @return array<string,mixed>
     */
    private function usagePayload(): array
    {
        // العدّاد الموثوق عبر كلّ المستهلكين (المصدر الوحيد: MediaUsage) متى حُمِّلت
        // عدّادات withCount/loadCount. null ⇒ لم يُطلَب الاستخدام في هذا السياق.
        $count = MediaUsage::sumLoadedCounts($this->resource);

        // التفصيل: علاقتا المحتوى مُحمَّلتان → نبني «أين يُستخدَم» (مقالات/تغطيات حيّة).
        if ($this->resource->relationLoaded('articles') && $this->resource->relationLoaded('liveUpdates')) {
            $usages = [];
            foreach ($this->articles as $a) {
                $usages[] = [
                    'context' => 'article',
                    'type' => $a->type->value,
                    'id' => $a->id,
                    'title' => $a->title,
                ];
            }
            foreach ($this->liveUpdates as $u) {
                $usages[] = [
                    'context' => 'live_update',
                    'id' => $u->id,
                    'article_id' => $u->article_id,
                    'title' => $u->article?->title,
                ];
            }

            return ['usage_count' => $count ?? count($usages), 'usages' => $usages];
        }

        // القائمة: عدّادات withCount متوفّرة → الإجماليّ عبر كلّ المستهلكين.
        if ($count !== null) {
            return ['usage_count' => $count];
        }

        return [];
    }
}
