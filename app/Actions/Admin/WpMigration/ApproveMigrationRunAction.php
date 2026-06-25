<?php

declare(strict_types=1);

namespace App\Actions\Admin\WpMigration;

use App\Http\Resources\Admin\WpMigration\MigrationRunResource;
use App\Models\MigrationRun;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * يعتمد المُشغِّل المعاينة الحالية ويثبّت سياسة التعارض — يُسلِّح بوّابة التنفيذ.
 * يُرفض الاعتماد إن كانت المعاينة قديمة/غائبة (لا تنفيذ على تخطيط بائت).
 */
class ApproveMigrationRunAction
{
    public function handle(MigrationRun $run, string $conflictPolicy): JsonResponse
    {
        if (! $run->previewIsCurrent()) {
            return ApiResponse::error(__('wp_migration.preview.stale'), [], 422);
        }

        $run->update([
            'conflict_policy' => $conflictPolicy,
            'approved_at' => now(),
        ]);

        return ApiResponse::success(__('wp_migration.preview.approved'), new MigrationRunResource($run->fresh()));
    }
}
