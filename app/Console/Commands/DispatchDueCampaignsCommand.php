<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Notifications\Actions\DispatchDueCampaignsAction;
use Illuminate\Console\Command;

/**
 * يُفعّل الحملات المجدولة المستحقّة — يُدار عبر SchedulerRegistry (everyMinute). idempotent
 * (انتقال حالة ذرّيّ) وآمن عند عدم وجود مستحقّ.
 */
class DispatchDueCampaignsCommand extends Command
{
    protected $signature = 'notifications:dispatch-due';

    protected $description = 'Dispatch due scheduled notification campaigns.';

    public function handle(DispatchDueCampaignsAction $action): int
    {
        $count = $action->handle();

        $this->info("Dispatched {$count} due campaign(s).");

        return self::SUCCESS;
    }
}
