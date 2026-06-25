<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Admin\Content\PublishDueArticlesAction;
use Illuminate\Console\Command;

/**
 * ناشِر المقالات المجدوَلة — يُدار عبر SchedulerRegistry (registry-driven).
 */
class PublishDueArticlesCommand extends Command
{
    protected $signature = 'articles:publish-due';

    protected $description = 'Publish scheduled articles whose published_at is due (idempotent, locked).';

    public function handle(PublishDueArticlesAction $action): int
    {
        $count = $action->handle();

        $this->info("Published {$count} due article(s).");

        return self::SUCCESS;
    }
}
