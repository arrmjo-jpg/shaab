<?php

declare(strict_types=1);

namespace App\Actions\Admin\WpMigration;

use App\Http\Resources\Admin\WpMigration\MigrationRunResource;
use App\Models\MigrationRun;
use App\Support\Responses\ApiResponse;
use App\Support\WpMigration\MigrationPreviewBuilder;
use App\Support\WpMigration\WpSourceInspector;
use Illuminate\Http\JsonResponse;

/**
 * يولّد معاينة الأثر (للقراءة فقط) من التنسيب الحالي ويخزّنها + طابع التوليد.
 * يتطلّب تنسيباً مُضمَّناً واحداً على الأقل واتصالاً حيّاً بالمصدر.
 */
class GeneratePreviewAction
{
    public function handle(MigrationRun $run): JsonResponse
    {
        $hasIncluded = $run->categoryMaps()->whereIn('mode', ['news', 'articles'])->exists();
        if (! $hasIncluded) {
            return ApiResponse::error(__('wp_migration.preview.no_mappings'), [], 422);
        }

        if (! WpSourceInspector::for($run)->canConnect()) {
            return ApiResponse::error(__('wp_migration.connection.failed'), [], 422);
        }

        $preview = MigrationPreviewBuilder::for($run)->build();

        $run->update([
            'preview' => $preview,
            'preview_generated_at' => now(),
        ]);

        return ApiResponse::success(__('wp_migration.preview.generated'), new MigrationRunResource($run->fresh()));
    }
}
