<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Admin\Advertising\TickAdCampaignsAction;
use Illuminate\Console\Command;

/**
 * يطبّق انتقالات دورة حياة الحملات التلقائية المستحقّة — يُدار عبر SchedulerRegistry
 * (everyMinute). idempotent وآمن عند عدم وجود مستحقّ.
 */
class TickAdCampaignsCommand extends Command
{
    protected $signature = 'ads:campaigns-tick';

    protected $description = 'Apply due automatic campaign lifecycle transitions (activate at window start, complete at window end).';

    public function handle(TickAdCampaignsAction $action): int
    {
        $count = $action->handle();

        $this->info("Transitioned {$count} campaign(s).");

        return self::SUCCESS;
    }
}
