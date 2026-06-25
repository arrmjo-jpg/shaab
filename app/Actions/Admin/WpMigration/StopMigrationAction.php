<?php

declare(strict_types=1);

namespace App\Actions\Admin\WpMigration;

use App\Enums\MigrationRunStatus;
use App\Http\Resources\Admin\WpMigration\MigrationRunResource;
use App\Models\MigrationRun;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * إيقاف آمن (قاعدة #7): running أو paused → stopping. لا قتل وسط معاملة — المُوزِّع
 * يكفّ عن إطلاق عمل جديد والطائر يُكمل بأمان (الإبقاء ذرّيّ لكل منشور). الحالة
 * stopping قابلة للاستئناف (تشغيلة لمرّة واحدة: قد يوقف المُشغِّل لإصلاح ثمّ يستأنف).
 */
class StopMigrationAction
{
    public function handle(MigrationRun $run): JsonResponse
    {
        if (! in_array($run->status, [MigrationRunStatus::Running, MigrationRunStatus::Paused], true)) {
            return ApiResponse::error(__('wp_migration.run.not_stoppable'), [], 422);
        }

        $run->forceFill([
            'status' => MigrationRunStatus::Stopping->value,
            'timeline' => $run->withEvent('stopping'),
        ])->save();

        return ApiResponse::success(__('wp_migration.run.stopped'), new MigrationRunResource($run->fresh()));
    }
}
