<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Admin\Broadcast\PublishDueBroadcastsAction;
use Illuminate\Console\Command;

/**
 * بدء البثّ المجدوَل المستحقّ — يُدار عبر SchedulerRegistry (registry-driven).
 * نطاق-حالة فقط (لا تشغيل بثّ — مصدر خارجي).
 */
class PublishDueBroadcastsCommand extends Command
{
    protected $signature = 'broadcasts:go-live-due';

    protected $description = 'Transition scheduled broadcasts whose scheduled_at is due to live (idempotent, locked, domain-state only — no stream start).';

    public function handle(PublishDueBroadcastsAction $action): int
    {
        $count = $action->handle();

        $this->info("Transitioned {$count} due broadcast(s) to live.");

        return self::SUCCESS;
    }
}
