<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Notifications\Health\ChannelHealthProbe;
use Illuminate\Console\Command;

/**
 * يفحص صحّة قنوات الإشعارات ويُديم effective_state — يُدار عبر SchedulerRegistry (كلّ 10د).
 * idempotent وآمن (يُطلق system.alert فقط عند الدخول في degraded).
 */
class ProbeChannelsCommand extends Command
{
    protected $signature = 'notifications:probe-channels';

    protected $description = 'Probe notification channel health and persist effective_state.';

    public function handle(ChannelHealthProbe $probe): int
    {
        $probe->probeAll();

        $this->info('Notification channels probed.');

        return self::SUCCESS;
    }
}
