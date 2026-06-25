<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Admin\Epaper\PublishDueEpapersAction;
use Illuminate\Console\Command;

class PublishDueEpapersCommand extends Command
{
    protected $signature = 'epapers:publish-due';

    protected $description = 'Publish scheduled epaper issues whose published_at is due (locked, idempotent).';

    public function handle(PublishDueEpapersAction $action): int
    {
        $count = $action->handle();
        $this->info("Published {$count} due epaper issue(s).");

        return self::SUCCESS;
    }
}
