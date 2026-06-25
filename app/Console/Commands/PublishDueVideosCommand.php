<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Admin\VideoLibrary\PublishDueVideosAction;
use Illuminate\Console\Command;

/**
 * ناشِر فيديوهات المكتبة المجدوَلة — يُدار عبر SchedulerRegistry (registry-driven).
 */
class PublishDueVideosCommand extends Command
{
    protected $signature = 'videos:publish-due';

    protected $description = 'Publish scheduled videos whose published_at is due (idempotent, locked, ready-media only).';

    public function handle(PublishDueVideosAction $action): int
    {
        $count = $action->handle();

        $this->info("Published {$count} due video(s).");

        return self::SUCCESS;
    }
}
