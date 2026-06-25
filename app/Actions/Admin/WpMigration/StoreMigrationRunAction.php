<?php

declare(strict_types=1);

namespace App\Actions\Admin\WpMigration;

use App\Enums\MigrationRunStatus;
use App\Http\Resources\Admin\WpMigration\MigrationRunResource;
use App\Models\MigrationRun;
use App\Support\Responses\ApiResponse;
use App\Support\WpMigration\WpSourceInspector;
use Illuminate\Http\JsonResponse;

/**
 * يُنشئ تشغيلة ترحيل بعد التحقّق من الاتصال واكتشاف البادئة (كلمة المرور مُعمّاة).
 */
class StoreMigrationRunAction
{
    /** @param  array<string,mixed>  $validated */
    public function handle(array $validated): JsonResponse
    {
        $run = new MigrationRun([
            'name' => $validated['name'] ?? 'WordPress import',
            'db_host' => $validated['db_host'],
            'db_port' => (int) ($validated['db_port'] ?? 3306),
            'db_name' => $validated['db_name'],
            'db_username' => $validated['db_username'],
            'db_password' => $validated['db_password'] ?? '',
            'table_prefix' => $validated['table_prefix'] ?? null,
            'uploads_path' => $validated['uploads_path'] ?? null,
            'status' => MigrationRunStatus::Draft->value,
        ]);

        $inspector = WpSourceInspector::for($run);
        if (! $inspector->canConnect()) {
            return ApiResponse::error(__('wp_migration.connection.failed'), [], 422);
        }

        $prefix = $run->table_prefix ?: $inspector->detectPrefix();
        if ($prefix === null) {
            return ApiResponse::error(__('wp_migration.connection.not_wordpress'), [], 422);
        }

        $run->table_prefix = $prefix;
        $run->save();

        return ApiResponse::success(__('wp_migration.run.created'), new MigrationRunResource($run), 201);
    }
}
