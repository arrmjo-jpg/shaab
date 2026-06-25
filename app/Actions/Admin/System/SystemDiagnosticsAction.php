<?php

declare(strict_types=1);

namespace App\Actions\Admin\System;

use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * تشخيص تشغيلي آمن للمشرف — حقائق وقت التشغيل دون أي أسرار (لا مفاتيح/كلمات/
 * اعتماديات). يكشف: بيئة التطبيق، إصدارات، مشغّلات (كاش/طابور/جلسة/قاعدة)، دعم
 * وسوم الكاش، حالة وضع الصيانة، صحّة الاتصال بقاعدة البيانات والكاش، نبض المُجدوِل.
 *
 * هدفه «الوضوح التشغيلي»: لقطة سريعة لتشخيص الحالة والاسترداد، تُكمّل لوحة الرصد
 * (ops-overview) وفحوصات الصحّة (system/health) دون تكرارها أو كشف معلومات حسّاسة.
 */
class SystemDiagnosticsAction
{
    public function handle(): JsonResponse
    {
        return ApiResponse::success(data: [
            'app' => [
                'environment' => App::environment(),
                'laravel_version' => App::version(),
                'php_version' => PHP_VERSION,
                'debug' => (bool) config('app.debug'),
                'locale' => config('app.locale'),
                'timezone' => config('app.timezone'),
                'url' => config('app.url'),
            ],
            'maintenance' => [
                // التطبيق في وضع الصيانة؟ التنفيذ يتولّاه وسيط Laravel (503 تلقائياً).
                'down' => App::isDownForMaintenance(),
            ],
            'drivers' => [
                'cache' => config('cache.default'),
                'queue' => config('queue.default'),
                'session' => config('session.driver'),
                'database' => config('database.default'),
                'mail' => config('mail.default'),
            ],
            'cache' => [
                'supports_tagging' => $this->cacheSupportsTagging(),
            ],
            'connectivity' => [
                'database' => $this->databaseHealthy(),
                'cache' => $this->cacheHealthy(),
            ],
            'queue' => [
                'pending' => Schema::hasTable('jobs') ? (int) DB::table('jobs')->count() : 0,
                'failed' => Schema::hasTable('failed_jobs') ? (int) DB::table('failed_jobs')->count() : 0,
            ],
            'scheduler' => $this->scheduler(),
            'opcache' => function_exists('opcache_get_status'),
            'checked_at' => now()->toIso8601String(),
        ]);
    }

    private function cacheSupportsTagging(): bool
    {
        try {
            Cache::tags(['__diag__'])->get('probe');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function databaseHealthy(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function cacheHealthy(): bool
    {
        try {
            Cache::store()->get('__diag_probe__');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function scheduler(): array
    {
        if (! Schema::hasTable('scheduled_tasks')) {
            return ['tasks' => 0, 'last_run_at' => null];
        }

        $row = DB::table('scheduled_tasks')
            ->selectRaw('COUNT(*) as total, MAX(last_run_at) as last_run_at')
            ->first();

        return [
            'tasks' => (int) ($row->total ?? 0),
            'last_run_at' => $row->last_run_at ?? null,
        ];
    }
}
