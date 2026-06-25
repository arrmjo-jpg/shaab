<?php

declare(strict_types=1);

namespace App\Actions\Admin\System;

use App\Support\Media\RemoteStorage;
use App\Support\Media\RemoteStorageHealth;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * لوحة رصد تشغيلية موحّدة (قراءة فقط، عدّادات رخيصة): صحّة الطابور، المهام الفاشلة،
 * متراكم مزامنة الوسائط، صحّة المرآة، الترميز العالق، فشل المرآة، نبض المُجدوِل.
 *
 * أداء: عدّادات DB مُجمَّعة (لا تحميل صفوف)؛ غير مُخزَّن مؤقتاً (لقطة حيّة للمشغّل).
 */
class OpsOverviewAction
{
    public function handle(): JsonResponse
    {
        $stuckMinutes = (int) config('performance.media.stuck_processing_minutes', 60);

        // ── الطابور والمهام الفاشلة ──
        $pendingJobs = Schema::hasTable('jobs') ? (int) DB::table('jobs')->count() : 0;
        $failedJobs = Schema::hasTable('failed_jobs') ? (int) DB::table('failed_jobs')->count() : 0;

        // ── متراكم مزامنة الوسائط (حالة المرآة) ──
        $sync = DB::table('media_assets')
            ->groupBy('remote_sync_status')
            ->selectRaw('remote_sync_status, COUNT(*) as aggregate')
            ->pluck('aggregate', 'remote_sync_status');
        $pending = (int) ($sync['pending'] ?? 0);
        $failedMirror = (int) ($sync['failed'] ?? 0);

        // ── معالجة الفيديو ──
        $stuckTranscoding = (int) DB::table('media_assets')
            ->where('processing_status', 'processing')
            ->where('updated_at', '<', now()->subMinutes($stuckMinutes))
            ->count();
        $failedTranscode24h = (int) DB::table('media_assets')
            ->where('processing_status', 'failed')
            ->where('updated_at', '>=', now()->subDay())
            ->count();

        // ── المُجدوِل (نبض + فشل آخر تشغيل) ──
        $scheduler = DB::table('scheduled_tasks')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN last_status = 'failed' THEN 1 ELSE 0 END) as failed_last_run")
            ->selectRaw('MAX(last_run_at) as last_run_at')
            ->first();

        return ApiResponse::success(data: [
            'queue' => [
                'pending' => $pendingJobs,
                'failed' => $failedJobs,
            ],
            'media' => [
                'sync_pending' => $pending,
                'sync_syncing' => (int) ($sync['syncing'] ?? 0),
                'sync_failed' => $failedMirror,
                'sync_synced' => (int) ($sync['synced'] ?? 0),
                'unsynced' => $pending + $failedMirror,
                'stuck_transcoding' => $stuckTranscoding,
                'failed_transcode_24h' => $failedTranscode24h,
                'failed_mirror' => $failedMirror,
            ],
            'remote_healthy' => RemoteStorage::enabled() ? RemoteStorageHealth::isHealthy() : null,
            'scheduler' => [
                'tasks' => (int) ($scheduler->total ?? 0),
                'failed_last_run' => (int) ($scheduler->failed_last_run ?? 0),
                'last_run_at' => $scheduler->last_run_at ?? null,
            ],
        ]);
    }
}
