<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Sport\SyncFollowedFixturesAction;
use Illuminate\Console\Command;

/**
 * يزامن مرآة مواعيد 365 للكيانات المتابَعة (نظام إشعارات «تابع»). يُدار عبر SchedulerRegistry (كل ١٠د).
 * idempotent، آمن عند الفراغ (لا متابعات ⇒ صفر نداءات).
 */
class SyncFollowedFixturesCommand extends Command
{
    protected $signature = 'follows:sync-fixtures';

    protected $description = 'Sync 365 fixtures mirror for followed teams/competitions/players/matches.';

    public function handle(SyncFollowedFixturesAction $action): int
    {
        $count = $action->handle();

        $this->info("Synced {$count} followed fixture(s).");

        return self::SUCCESS;
    }
}
