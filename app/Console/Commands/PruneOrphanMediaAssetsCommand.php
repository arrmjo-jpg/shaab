<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Admin\Media\PruneOrphanMediaAssetsAction;
use Illuminate\Console\Command;

/**
 * تنظيف أصول المكتبة المرحّلة المهجورة (غير مُسنَدة + أقدم من TTL).
 * يُدار عبر SchedulerRegistry (registry-driven).
 */
class PruneOrphanMediaAssetsCommand extends Command
{
    protected $signature = 'media:prune-orphans {--hours= : تجاوز TTL الافتراضي بالساعات}';

    protected $description = 'Delete staged-but-unattached media library assets older than the TTL.';

    public function handle(PruneOrphanMediaAssetsAction $action): int
    {
        $hours = $this->option('hours') !== null ? (int) $this->option('hours') : null;

        $count = $action->handle($hours);

        $this->info("Pruned {$count} orphan media asset(s).");

        return self::SUCCESS;
    }
}
