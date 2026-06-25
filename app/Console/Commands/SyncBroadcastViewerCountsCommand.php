<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Admin\Broadcast\SyncBroadcastViewerCountsAction;
use Illuminate\Console\Command;

/**
 * يزامن لقطة عدّاد المشاهدين المباشرين من محرّك الحضور (الكاش) إلى قاعدة البيانات —
 * يُدار عبر SchedulerRegistry (everyMinute). idempotent وآمن عند الفراغ.
 */
class SyncBroadcastViewerCountsCommand extends Command
{
    protected $signature = 'broadcasts:sync-viewer-counts';

    protected $description = 'Sync approximate live viewer counts from the presence engine (cache) into the DB snapshot.';

    public function handle(SyncBroadcastViewerCountsAction $action): int
    {
        $synced = $action->handle();

        $this->info("Synced viewer counts for {$synced} live broadcast(s).");

        return self::SUCCESS;
    }
}
