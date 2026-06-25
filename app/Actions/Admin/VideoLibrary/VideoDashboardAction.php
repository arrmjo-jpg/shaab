<?php

declare(strict_types=1);

namespace App\Actions\Admin\VideoLibrary;

use App\Enums\VideoStatus;
use App\Models\Video;
use App\Models\VideoCategory;
use App\Models\VideoPlaylist;
use App\Support\Cache\CacheTtl;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * لوحة مكتبة الفيديو — مجاميع رخيصة مفيدة للواجهة/الموبايل: عدّادات الحالة، توزيع
 * المصدر، صحّة المعالجة، المميَّز، المشاهدات، القوائم، التصنيفات، وأعلى الفيديوهات.
 */
class VideoDashboardAction
{
    public function handle(): JsonResponse
    {
        // كاش قصير المدى: ~10 مجاميع لكل تحميل — يحدّ التكرار دون تغيير العقد.
        $data = Cache::remember('video:dashboard:v1', CacheTtl::SHORT, fn (): array => $this->compute());

        return ApiResponse::success(data: $data);
    }

    /** @return array<string,mixed> */
    private function compute(): array
    {
        $byStatus = Video::query()
            ->groupBy('status')->selectRaw('status, COUNT(*) as aggregate')
            ->pluck('aggregate', 'status');

        $bySource = Video::query()
            ->groupBy('source_type')->selectRaw('source_type, COUNT(*) as aggregate')
            ->pluck('aggregate', 'source_type');

        $processingHealth = [
            'processing' => $this->countByAssetStatus('processing'),
            'failed' => $this->countByAssetStatus('failed'),
            'ready' => $this->countByAssetStatus('ready'),
        ];

        $topVideos = Video::query()->public()
            ->orderByDesc('views_count')
            ->limit(5)
            ->get(['id', 'title', 'slug', 'locale', 'views_count'])
            ->map(fn (Video $v): array => [
                'id' => $v->id,
                'title' => $v->title,
                'slug' => $v->slug,
                'locale' => $v->locale,
                'views_count' => $v->views_count,
            ])->all();

        $topCategories = VideoCategory::query()
            ->withCount('videos')
            ->orderByDesc('videos_count')
            ->limit(5)
            ->get(['id', 'name', 'slug'])
            ->map(fn (VideoCategory $c): array => [
                'id' => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
                'videos_count' => $c->videos_count,
            ])->all();

        return [
            'status_counts' => [
                'total' => (int) Video::query()->count(),
                'draft' => (int) ($byStatus[VideoStatus::Draft->value] ?? 0),
                'scheduled' => (int) ($byStatus[VideoStatus::Scheduled->value] ?? 0),
                'published' => (int) ($byStatus[VideoStatus::Published->value] ?? 0),
                'archived' => (int) ($byStatus[VideoStatus::Archived->value] ?? 0),
            ],
            'source_distribution' => [
                'uploaded' => (int) ($bySource['uploaded'] ?? 0),
                'youtube' => (int) ($bySource['youtube'] ?? 0),
                'vimeo' => (int) ($bySource['vimeo'] ?? 0),
                'direct_mp4' => (int) ($bySource['direct_mp4'] ?? 0),
            ],
            'processing_health' => $processingHealth,
            'featured' => (int) Video::query()->where('is_featured', true)->count(),
            'total_views' => (int) Video::query()->sum('views_count'),
            'playlists' => [
                'total' => (int) VideoPlaylist::query()->count(),
                'published' => (int) VideoPlaylist::query()->where('status', VideoStatus::Published->value)->count(),
                'featured' => (int) VideoPlaylist::query()->where('is_featured', true)->count(),
            ],
            'categories' => [
                'total' => (int) VideoCategory::query()->count(),
                'active' => (int) VideoCategory::query()->where('is_active', true)->count(),
            ],
            'top_videos' => $topVideos,
            'top_categories' => $topCategories,
        ];
    }

    /** عدّ الفيديوهات المرفوعة حسب حالة معالجة أصلها. */
    private function countByAssetStatus(string $status): int
    {
        return (int) Video::query()
            ->where('source_type', 'uploaded')
            ->whereHas('mediaAsset', fn ($q) => $q->where('processing_status', $status))
            ->count();
    }
}
