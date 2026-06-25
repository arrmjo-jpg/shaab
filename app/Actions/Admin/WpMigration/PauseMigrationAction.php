<?php

declare(strict_types=1);

namespace App\Actions\Admin\WpMigration;

use App\Enums\MigrationRunStatus;
use App\Http\Resources\Admin\WpMigration\MigrationRunResource;
use App\Models\MigrationRun;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * إيقاف مؤقّت (قاعدة #6): يكفّ المُوزِّع عن إطلاق عمل جديد، والطائر يُكمل بأمان.
 * انتقال running → paused فقط؛ غير ذلك ⇒ 422. الاستئناف يعيد إطلاق المُوزِّع.
 */
class PauseMigrationAction
{
    public function handle(MigrationRun $run): JsonResponse
    {
        if ($run->status !== MigrationRunStatus::Running) {
            return ApiResponse::error(__('wp_migration.run.not_running'), [], 422);
        }

        $run->forceFill([
            'status' => MigrationRunStatus::Paused->value,
            'timeline' => $run->withEvent('paused'),
        ])->save();

        return ApiResponse::success(__('wp_migration.run.paused'), new MigrationRunResource($run->fresh()));
    }
}
