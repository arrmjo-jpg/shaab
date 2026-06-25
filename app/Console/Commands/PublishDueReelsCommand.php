<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Admin\Content\PublishDueReelsAction;
use Illuminate\Console\Command;

/**
 * ناشِر الريلز المجدوَلة — يُدار عبر SchedulerRegistry (registry-driven).
 */
class PublishDueReelsCommand extends Command
{
    protected $signature = 'reels:publish-due';

    protected $description = 'Publish scheduled reels whose published_at is due (idempotent, locked, ready-media only).';

    public function handle(PublishDueReelsAction $action): int
    {
        $count = $action->handle();

        $this->info("Published {$count} due reel(s).");

        return self::SUCCESS;
    }
}
