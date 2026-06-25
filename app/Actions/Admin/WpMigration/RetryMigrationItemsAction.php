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
 * إعادة محاولة عناصر مُختارة/كل الفاشلة/كل الجزئية (#6). يعيد العناصر المؤهَّلة
 * (failed/partial/skipped) إلى pending ويصفّر المحاولات (مطالبة المهمة تشترط
 * attempts<cap — فالتصفير يفتح محاولة نظيفة حتى لمن بلغ السقف). ثمّ يُعيد فتح
 * التشغيلة (running) إن لم تكن جارية ويطلق المُوزِّع — الاستئناف الحتميّ من الدفتر.
 *
 * لا عناصر مطابقة ⇒ 422 (لا تغيير حالة التشغيلة): لا إعادة فتح فارغة.
 *
 * @param  array<int,int>  $ids
 */
class RetryMigrationItemsAction
{
    public function handle(MigrationRun $run, string $mode, array $ids = []): JsonResponse
    {
        $query = MigrationItem::query()->where('run_id', $run->id);

        match ($mode) {
            'failed' => $query->where('status', MigrationItemStatus::Failed->value),
            'partial' => $query->where('status', MigrationItemStatus::Partial->value),
            default => $query->whereIn('id', $ids)->whereIn('status', [
                MigrationItemStatus::Failed->value,
                MigrationItemStatus::Partial->value,
                MigrationItemStatus::Skipped->value,
            ]),
        };

        $reset = $query->update([
            'status' => MigrationItemStatus::Pending->value,
            'attempts' => 0,
            'last_step' => 'retry',
            'updated_at' => now(),
        ]);

        if ($reset === 0) {
            return ApiResponse::error(__('wp_migration.run.nothing_to_retry'), [], 422);
        }

        // إعادة فتح التشغيلة إن لزم (مكتملة/موقوفة/جارٍ إيقافها) ثمّ إطلاق المُوزِّع.
        if ($run->status !== MigrationRunStatus::Running) {
            $run->forceFill([
                'status' => MigrationRunStatus::Running->value,
                'finished_at' => null,
                'timeline' => $run->withEvent('retried'),
            ])->save();
        }

        DispatchMigrationChunkJob::dispatch($run->id);

        return ApiResponse::success(__('wp_migration.run.retry_queued'), new MigrationRunResource($run->fresh()), 200, [
            'retried' => $reset,
        ]);
    }
}
