<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Admin\Broadcast\MonitorBroadcastHealthAction;
use Illuminate\Console\Command;

/**
 * مراقبة صحّة مصادر البثّ — يُدار عبر SchedulerRegistry كل دقيقة؛ التواتر المتدرّج
 * (live أسرع من tv/radio) مُطبَّق داخل الـ Action (يفحص المستحقّ فقط).
 */
class MonitorBroadcastHealthCommand extends Command
{
    protected $signature = 'broadcasts:health-check';

    protected $description = 'Probe live/failed broadcast sources (SSRF-safe, tiered cadence) and apply failure/recovery transitions.';

    public function handle(MonitorBroadcastHealthAction $action): int
    {
        $summary = $action->handle();

        $this->info("Checked {$summary['checked']}, failed {$summary['failed']}, recovered {$summary['recovered']}.");

        return self::SUCCESS;
    }
}
