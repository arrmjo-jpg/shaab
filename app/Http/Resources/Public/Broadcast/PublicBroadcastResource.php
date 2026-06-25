<?php

declare(strict_types=1);

namespace App\Http\Resources\Public\Broadcast;

use App\Enums\BroadcastStatus;
use App\Support\Content\BroadcastSeoBuilder;
use Illuminate\Http\Request;

/**
 * مورد تفاصيل البثّ العام — البطاقة + كتلة SEO الكاملة + عقد التشغيل (playback) الذي
 * يحسم قرار المنتج لكل حالة في كائن واحد صريح:
 *
 *   live      → source {type,url} يُكشَف (تشغيل مباشر — مصدر خارجي موثوق عام).
 *   upcoming  → starts_at فقط (زمن مطلق للعدّ التنازلي) — لا مصدر بعد.
 *   ended     → vod اختياري (تسجيل نهائي عام) — لا مصدر مباشر.
 *   offline   → لا مصدر (متوقّف مؤقّتاً) — صفحة آمنة بحالتها.
 *   failed    → لا مصدر (لا نكشف المعطوب) — صفحة آمنة بحالتها.
 *
 * أمان: source_url لا يظهر إطلاقاً خارج حالة live؛ ولا تُكشَف أي بيانات صحّة/إدارة.
 */
class PublicBroadcastResource extends PublicBroadcastCardResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'playback' => $this->playback(),
            'seo' => BroadcastSeoBuilder::build($this->resource),
        ]);
    }

    /** @return array<string,mixed> */
    private function playback(): array
    {
        $status = $this->status;

        return [
            'state' => $this->playbackState($status),
            // المصدر يُكشَف فقط أثناء البثّ المباشر (مصدر خارجي موثوق عام).
            'source' => $status === BroadcastStatus::Live
                ? ['type' => $this->source_type->value, 'url' => $this->source_url]
                : null,
            // قادم: زمن البدء المطلق (آمن للكاش — العميل يحسب العدّ التنازلي بساعته).
            'starts_at' => $status === BroadcastStatus::Scheduled
                ? $this->scheduled_at?->toISOString()
                : null,
            // منتهٍ: ربط VOD اختياري — يظهر فقط إن كان الفيديو عامّاً قابلاً للتشغيل
            // (القيد مفروض عند التحميل المُسبَق في الـ Action، فلا نسرّب فيديو خاصّاً).
            'vod' => $status === BroadcastStatus::Ended && $this->vodVideo !== null
                ? [
                    'id' => $this->vodVideo->id,
                    'slug' => $this->vodVideo->slug,
                    'canonical_path' => $this->vodVideo->canonicalPath(),
                ]
                : null,
        ];
    }

    private function playbackState(BroadcastStatus $status): string
    {
        return match ($status) {
            BroadcastStatus::Live => 'live',
            BroadcastStatus::Scheduled => 'upcoming',
            BroadcastStatus::Ended => 'ended',
            BroadcastStatus::Offline => 'offline',
            BroadcastStatus::Failed => 'failed',
            // Draft/Archived لا يصلان للتفاصيل العامة (publiclyVisible يحجبهما).
            default => 'unavailable',
        };
    }
}
