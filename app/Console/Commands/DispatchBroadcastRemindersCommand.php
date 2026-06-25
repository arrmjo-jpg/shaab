<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Admin\Broadcast\DispatchBroadcastRemindersAction;
use Illuminate\Console\Command;

/**
 * يُرسل تذكيرات البثّ المجدوَل المستحقّة (الداخلة نافذة التذكير) — يُدار عبر
 * SchedulerRegistry (everyMinute). idempotent، آمن عند الفراغ، ومنع تكرار عبر العلامة.
 */
class DispatchBroadcastRemindersCommand extends Command
{
    protected $signature = 'broadcasts:dispatch-reminders';

    protected $description = 'Dispatch per-event reminder notifications for scheduled broadcasts entering the reminder window.';

    public function handle(DispatchBroadcastRemindersAction $action): int
    {
        $count = $action->handle();

        $this->info("Dispatched reminders for {$count} broadcast(s).");

        return self::SUCCESS;
    }
}
