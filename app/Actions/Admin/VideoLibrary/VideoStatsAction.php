<?php

declare(strict_types=1);

namespace App\Actions\Admin\VideoLibrary;

use App\Models\Video;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * عدّادات بطاقات لوحة الفيديو (رخيصة، مُجمَّعة). المعالجة/الفشل من حالة الأصل المرفوع.
 */
class VideoStatsAction
{
    public function handle(): JsonResponse
    {
        $byStatus = Video::query()
            ->groupBy('status')
            ->selectRaw('status, COUNT(*) as aggregate')
            ->pluck('aggregate', 'status');

        $processing = Video::query()
            ->whereHas('mediaAsset', fn ($q) => $q->where('processing_status', 'processing'))
            ->count();

        $failed = Video::query()
            ->whereHas('mediaAsset', fn ($q) => $q->where('processing_status', 'failed'))
            ->count();

        return ApiResponse::success(data: [
            'total' => (int) Video::query()->count(),
            'published' => (int) ($byStatus['published'] ?? 0),
            'draft' => (int) ($byStatus['draft'] ?? 0),
            'scheduled' => (int) ($byStatus['scheduled'] ?? 0),
            'archived' => (int) ($byStatus['archived'] ?? 0),
            'processing' => $processing,
            'failed_processing' => $failed,
            'featured' => (int) Video::query()->where('is_featured', true)->count(),
            'total_views' => (int) Video::query()->sum('views_count'),
        ]);
    }
}
