<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Notifications\Actions\ReconcileStuckCampaignsAction;
use Illuminate\Console\Command;

/**
 * يصالح الحملات العالقة في Sending (شبكة أمان) — يُدار عبر SchedulerRegistry. idempotent وآمن.
 */
class ReconcileCampaignsCommand extends Command
{
    protected $signature = 'notifications:reconcile-campaigns';

    protected $description = 'Reconcile stuck (Sending) notification campaigns and channels.';

    public function handle(ReconcileStuckCampaignsAction $action): int
    {
        $count = $action->handle();

        $this->info("Reconciled {$count} stuck campaign(s).");

        return self::SUCCESS;
    }
}
