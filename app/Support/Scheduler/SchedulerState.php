<?php

declare(strict_types=1);

namespace App\Support\Scheduler;

/**
 * تسجيل نتيجة تشغيل مهمة (من خطّافات المُجدوِل أو التشغيل اليدوي).
 */
final class SchedulerState
{
    public static function markRunning(string $key): void
    {
        SchedulerRegistry::state($key)->forceFill([
            'last_status' => 'running',
            'last_run_at' => now(),
        ])->save();
    }

    public static function record(
        string $key,
        bool $success,
        ?float $startedAt = null,
        ?string $error = null
    ): void {
        $runtimeMs = $startedAt !== null
            ? (int) round((microtime(true) - $startedAt) * 1000)
            : null;

        SchedulerRegistry::state($key)->forceFill([
            'last_status' => $success ? 'success' : 'failed',
            'last_run_at' => now(),
            'last_runtime_ms' => $runtimeMs,
            'last_error' => $success ? null : ($error !== null ? mb_substr($error, 0, 1000) : null),
        ])->save();
    }
}
