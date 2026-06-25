<?php

declare(strict_types=1);

namespace App\Actions\Admin\WpMigration;

use App\Models\MigrationRun;
use App\Support\Responses\ApiResponse;
use App\Support\WpMigration\WpSourceInspector;
use Illuminate\Http\JsonResponse;

/**
 * اختبار اتصال المصدر بلا حفظ — يتحقّق من الاتصال/القراءة، يكتشف البادئة،
 * ويؤكّد نسخة ووردبريس. لا كتابات على المصدر ولا على AlphaCMS.
 */
class TestWpConnectionAction
{
    /** @param  array<string,mixed>  $creds */
    public function handle(array $creds): JsonResponse
    {
        $run = new MigrationRun([
            'db_host' => $creds['db_host'],
            'db_port' => (int) ($creds['db_port'] ?? 3306),
            'db_name' => $creds['db_name'],
            'db_username' => $creds['db_username'],
            'db_password' => $creds['db_password'] ?? '',
            'table_prefix' => $creds['table_prefix'] ?? null,
        ]);

        $inspector = WpSourceInspector::for($run);

        if (! $inspector->canConnect()) {
            return ApiResponse::error(__('wp_migration.connection.failed'), [], 422);
        }

        // اكتشاف البادئة إن لم يُحدّدها المُشغِّل، ثم إعادة التهيئة لتأكيد ووردبريس.
        $prefix = $run->table_prefix ?: $inspector->detectPrefix();
        if ($prefix !== null && $prefix !== $run->table_prefix) {
            $run->table_prefix = $prefix;
            $inspector = WpSourceInspector::for($run);
        }

        $isWordpress = $prefix !== null && $inspector->isWordpress();

        return ApiResponse::success(__('wp_migration.connection.ok'), [
            'connected' => true,
            'read_ok' => true,
            'wordpress_detected' => $isWordpress,
            'detected_prefix' => $prefix,
        ]);
    }
}
