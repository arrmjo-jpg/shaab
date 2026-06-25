<?php

declare(strict_types=1);

namespace App\Actions\Admin\VideoLibrary;

use App\Enums\VideoStatus;
use App\Models\Video;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * مركز عمليات مكتبة الفيديو — رؤية تشغيلية حقيقية وقابلة للتنفيذ:
 *   - صحّة المعالجة (عدّادات processing/failed للأصول المرفوعة).
 *   - قائمة الفيديوهات المحتاجة انتباهاً (وسائط فاشلة/قيد المعالجة) مع uuid لإعادة المعالجة.
 *   - صحّة طابور النشر: المجدوَل، المستحقّ الآن، والقادم القريب.
 * لا عناصر تجميلية — كل قيمة من قاعدة البيانات مباشرة.
 */
class VideoOperationsAction
{
    private const ATTENTION_LIMIT = 50;

    private const QUEUE_LIMIT = 20;

    public function handle(): JsonResponse
    {
        $processing = Video::query()
            ->whereHas('mediaAsset', fn ($q) => $q->where('processing_status', 'processing'))
            ->count();

        $failed = Video::query()
            ->whereHas('mediaAsset', fn ($q) => $q->where('processing_status', 'failed'))
            ->count();

        $needsAttention = Video::query()
            ->where('source_type', 'uploaded')
            ->whereHas('mediaAsset', fn ($q) => $q->whereIn('processing_status', ['failed', 'processing']))
            ->with('mediaAsset:id,uuid,processing_status')
            ->latest('updated_at')
            ->limit(self::ATTENTION_LIMIT)
            ->get(['id', 'title', 'locale', 'media_asset_id', 'source_type', 'updated_at'])
            ->map(fn (Video $v): array => [
                'id' => $v->id,
                'title' => $v->title,
                'locale' => $v->locale,
                'media_uuid' => $v->mediaAsset?->uuid,
                'processing_status' => $v->mediaAsset?->processing_status,
                'updated_at' => $v->updated_at?->toISOString(),
            ])->all();

        $scheduled = VideoStatus::Scheduled->value;
        $scheduledTotal = Video::query()->where('status', $scheduled)->count();
        $dueNow = Video::query()->where('status', $scheduled)->where('published_at', '<=', now())->count();

        $upcoming = Video::query()
            ->where('status', $scheduled)
            ->orderBy('published_at')
            ->limit(self::QUEUE_LIMIT)
            ->get(['id', 'title', 'locale', 'published_at'])
            ->map(fn (Video $v): array => [
                'id' => $v->id,
                'title' => $v->title,
                'locale' => $v->locale,
                'published_at' => $v->published_at?->toISOString(),
                'overdue' => $v->published_at !== null && $v->published_at->isPast(),
            ])->all();

        return ApiResponse::success(data: [
            'processing_health' => [
                'processing' => $processing,
                'failed' => $failed,
            ],
            'needs_attention' => $needsAttention,
            'publish_queue' => [
                'scheduled_total' => $scheduledTotal,
                'due_now' => $dueNow,
                'upcoming' => $upcoming,
            ],
        ]);
    }
}
