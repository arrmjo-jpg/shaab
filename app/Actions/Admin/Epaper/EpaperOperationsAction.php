<?php

declare(strict_types=1);

namespace App\Actions\Admin\Epaper;

use App\Enums\EpaperOcrStatus;
use App\Models\Epaper;
use App\Support\Epaper\EpaperSearchIndexer;
use App\Support\Media\RemoteStorage;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Meilisearch\Exceptions\ApiException;
use Throwable;

/**
 * رؤية تشغيليّة لحظيّة للجريدة (Final completion — البند C): صحّة OCR (تفصيل الحالات +
 * الفاشل + العالق + التراكم)، حالة محرّك البحث/الفهرس + هل يفهرس الآن، تراكم طوابير
 * الجريدة (search/media/analytics)، ومؤشّر التسليم البعيد. يكمّل فحوصات spatie-health
 * (التي تُطلِق الإنذارات) بلوحة عرضٍ فوريّة للمشغّل. أفضل-جهد: لا يسقط على تعذّر محرّك.
 */
class EpaperOperationsAction
{
    public function handle(): JsonResponse
    {
        return ApiResponse::success(__('epaper.operations.shown'), [
            'search' => $this->search(),
            'ocr' => $this->ocr(),
            'queues' => $this->queues(),
            'delivery' => ['remote_enabled' => RemoteStorage::enabled()],
            'checked_at' => now()->toISOString(),
        ]);
    }

    /** @return array<string,mixed> */
    private function search(): array
    {
        if (! EpaperSearchIndexer::enabled()) {
            return ['enabled' => false, 'reachable' => null, 'indexed_documents' => null, 'is_indexing' => false, 'state' => 'disabled'];
        }

        try {
            $stats = EpaperSearchIndexer::index()->stats();
            $docs = (int) ($stats['numberOfDocuments'] ?? 0);
            $hasPublished = Epaper::query()->published()->exists();

            return [
                'enabled' => true,
                'reachable' => true,
                'indexed_documents' => $docs,
                'is_indexing' => (bool) ($stats['isIndexing'] ?? false),
                'state' => ($hasPublished && $docs < 1) ? 'empty' : 'healthy',
            ];
        } catch (ApiException $e) {
            if ($e->errorCode === 'index_not_found') {
                return [
                    'enabled' => true, 'reachable' => true, 'indexed_documents' => 0, 'is_indexing' => false,
                    'state' => Epaper::query()->published()->exists() ? 'empty' : 'healthy',
                ];
            }

            return ['enabled' => true, 'reachable' => false, 'indexed_documents' => null, 'is_indexing' => false, 'state' => 'unreachable'];
        } catch (Throwable) {
            return ['enabled' => true, 'reachable' => false, 'indexed_documents' => null, 'is_indexing' => false, 'state' => 'unreachable'];
        }
    }

    /** @return array<string,mixed> */
    private function ocr(): array
    {
        $counts = Epaper::query()->selectRaw('ocr_status, COUNT(*) c')->groupBy('ocr_status')->pluck('c', 'ocr_status');

        $byStatus = [];
        foreach (EpaperOcrStatus::cases() as $case) {
            $byStatus[$case->value] = (int) ($counts[$case->value] ?? 0);
        }

        $stuckMinutes = max(1, (int) config('epaper.ocr.health.stuck_minutes', 30));
        $stuck = Epaper::query()
            ->where('ocr_status', EpaperOcrStatus::Processing->value)
            ->where('updated_at', '<', now()->subMinutes($stuckMinutes))
            ->count();

        return [
            'by_status' => $byStatus,
            'failed' => $byStatus[EpaperOcrStatus::Failed->value] ?? 0,
            'stuck' => $stuck,
            'backlog' => ($byStatus[EpaperOcrStatus::Pending->value] ?? 0) + ($byStatus[EpaperOcrStatus::Processing->value] ?? 0),
        ];
    }

    /**
     * عدّ تراكم الطوابير — أفضل-جهد من جدول jobs (دقيق حين QUEUE=database؛ مع redis
     * يكون صفراً، كما في System Diagnostics العامّة). لا يسقط على تعذّر الجدول.
     *
     * @return array<string,int>
     */
    private function queues(): array
    {
        try {
            $byQueue = DB::table('jobs')->selectRaw('queue, COUNT(*) c')->groupBy('queue')->pluck('c', 'queue');

            return [
                'pending' => (int) DB::table('jobs')->count(),
                'failed' => (int) DB::table('failed_jobs')->count(),
                'search' => (int) ($byQueue['search'] ?? 0),
                'media' => (int) ($byQueue['media'] ?? 0),
                'analytics' => (int) ($byQueue['analytics'] ?? 0),
            ];
        } catch (Throwable) {
            return ['pending' => 0, 'failed' => 0, 'search' => 0, 'media' => 0, 'analytics' => 0];
        }
    }
}
