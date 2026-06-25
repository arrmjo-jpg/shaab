<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Notifications\Actions\SyncEventCatalogAction;
use Illuminate\Console\Command;

/**
 * يُزامن EventCatalog (SoT الكوديّ) إلى notification_events + يضمن صفوف المصفوفة + يؤرشف المحذوف.
 * يُشغَّل عند النشر. idempotent.
 */
class SyncCatalogCommand extends Command
{
    protected $signature = 'notifications:sync-catalog';

    protected $description = 'Sync EventCatalog (source of truth) to notification_events and ensure matrix rows.';

    public function handle(SyncEventCatalogAction $action): int
    {
        $action->handle();

        $this->info('Notification event catalog synced.');

        return self::SUCCESS;
    }
}
