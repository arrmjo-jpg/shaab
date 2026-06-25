<?php

declare(strict_types=1);

namespace App\Actions\Admin\WpMigration;

use App\Enums\MigrationItemStatus;
use App\Enums\MigrationRunStatus;
use App\Http\Resources\Admin\WpMigration\MigrationRunResource;
use App\Jobs\WpMigration\DispatchMigrationChunkJob;
use App\Models\MigrationItem;
use App\Models\MigrationRun;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * استئناف التنفيذ من حالة الدفتر تماماً (قاعدة #11): paused أو stopping → running.
 *
 * العناصر التي طُولِب بها (queued) عند الإيقاف لم تُعالَج (المهمة تتخطّى حين لا running)؛
 * تُعاد إلى pending كي يلتقطها المُوزِّع فوراً دون انتظار مهلة الاسترداد. العناصر
 * processing الحيّة تُكمل وحدها، والميتة منها يستردّها المُوزِّع (stale reclaim).
 */
class ResumeMigrationAction
{
    public function handle(MigrationRun $run): JsonResponse
    {
        if (! in_array($run->status, [MigrationRunStatus::Paused, MigrationRunStatus::Stopping], true)) {
            return ApiResponse::error(__('wp_migration.run.not_resumable'), [], 422);
        }

        MigrationItem::query()
            ->where('run_id', $run->id)
            ->where('status', MigrationItemStatus::Queued->value)
            ->update(['status' => MigrationItemStatus::Pending->value, 'updated_at' => now()]);

        $run->forceFill([
            'status' => MigrationRunStatus::Running->value,
            'finished_at' => null,
            'timeline' => $run->withEvent('resumed'),
        ])->save();

        DispatchMigrationChunkJob::dispatch($run->id);

        return ApiResponse::success(__('wp_migration.run.resumed'), new MigrationRunResource($run->fresh()));
    }
}
