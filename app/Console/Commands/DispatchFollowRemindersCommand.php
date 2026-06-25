<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Sport\DispatchFollowRemindersAction;
use Illuminate\Console\Command;

/**
 * يُرسل تذكيرات ما قبل المباراة المستحقّة لمتابِعي الكيانات (نظام «تابع»). يُدار عبر SchedulerRegistry (كلّ دقيقة).
 * idempotent، آمن عند الفراغ، ومنع تكرار عبر follow_notifications.
 */
class DispatchFollowRemindersCommand extends Command
{
    protected $signature = 'follows:dispatch-reminders';

    protected $description = 'Dispatch pre-match reminder notifications to followers of teams/competitions/players/matches.';

    public function handle(DispatchFollowRemindersAction $action): int
    {
        $count = $action->handle();

        $this->info("Dispatched {$count} follow reminder(s).");

        return self::SUCCESS;
    }
}
