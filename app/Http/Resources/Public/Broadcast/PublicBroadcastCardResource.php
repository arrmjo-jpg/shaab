<?php

declare(strict_types=1);

namespace App\Http\Resources\Public\Broadcast;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * بطاقة بثّ عامة خفيفة — لِلقوائم العامة (/live · /tv · /radio). تحمل ما يلزم العرض
 * دون كتلة SEO الكاملة (تبقى حصراً على نقطة التفاصيل) ودون أي تسريب داخلي:
 *
 *   لا source_url (يُكشَف فقط في التفاصيل وللحالة live)، لا health (صحّة المصدر
 *   داخلية)، لا is_public/meta/created_by — حقول إدارة بحتة. viewer_count لقطة
 *   تقريبية (الزمن الحقيقي يأتي من Redis في B5).
 */
class PublicBroadcastCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // عدّادات التفاعل المُجمَّعة (B7) — تعتمد العدّاد المُحمَّل مسبقاً (لا N+1).
        $metrics = $this->engagementMetrics();

        return [
            'id' => $this->id,
            'kind' => $this->kind->value,
            'status' => $this->status->value,
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'description' => $this->description,
            'source_type' => $this->source_type->value,
            'is_featured' => $this->is_featured,
            'viewer_count' => $this->viewer_count,
            'metrics' => [
                'likes' => $metrics['likes'],
                'dislikes' => $metrics['dislikes'],
            ],
            'scheduled_at' => $this->scheduled_at?->toISOString(),
            'started_at' => $this->started_at?->toISOString(),
            'ended_at' => $this->ended_at?->toISOString(),
            'canonical_path' => $this->canonicalPath(),
            'share_image' => $this->shareImageUrl(),
            'category' => $this->whenLoaded('category', fn () => $this->category === null ? null : [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
            ]),
        ];
    }
}
