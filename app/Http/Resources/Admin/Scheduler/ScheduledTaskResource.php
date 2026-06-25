<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Scheduler;

use App\Support\Scheduler\SchedulerRegistry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * مهمة مجدوَلة = تعريف ثابت (من السجل) + حالة + حسابات backend
 * (next_run_at / health). الواجهة لا تحسب جدولة.
 */
class ScheduledTaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $def = SchedulerRegistry::find($this->key) ?? [];
        $enabled = (bool) $this->enabled;
        $cron = $def['cron'] ?? null;

        $nextRunAt = $enabled && $cron
            ? SchedulerRegistry::nextRunAt($cron)?->toISOString()
            : null;

        return [
            'key' => $this->key,
            'name' => __("scheduler.tasks.{$this->key}.name"),
            'description' => __("scheduler.tasks.{$this->key}.description"),
            'type' => 'command',
            'command' => $def['command'] ?? null,
            'cron' => $cron,
            'frequency' => __('scheduler.frequency.'.($def['frequency'] ?? 'custom')),
            'critical' => (bool) ($def['critical'] ?? false),
            'manual_run_allowed' => (bool) ($def['manual_run_allowed'] ?? false),
            'enabled' => $enabled,
            'notes' => $this->notes,
            'last_run_at' => $this->last_run_at?->toISOString(),
            'last_status' => $this->last_status,
            'last_runtime_ms' => $this->last_runtime_ms,
            'last_error' => $this->last_error,
            'next_run_at' => $nextRunAt,
            'health' => $this->computeHealth($enabled, $cron),
        ];
    }

    private function computeHealth(bool $enabled, ?string $cron): string
    {
        if (! $enabled) {
            return 'disabled';
        }
        if ($this->last_status === 'failed') {
            return 'failed';
        }
        if ($this->last_run_at === null || $this->last_status === 'never') {
            return 'never';
        }

        $prevExpected = $cron ? SchedulerRegistry::previousExpectedAt($cron) : null;
        if ($prevExpected !== null && $this->last_run_at->lt($prevExpected)) {
            return 'stale';
        }

        return 'healthy';
    }
}
