<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Sport\PollLiveFollowedMatchesAction;
use Illuminate\Console\Command;

/**
 * يستطلع المباريات الحيّة المتابَعة (المستحقّة عبر next_poll_at) ويُشعر بأحداثها (أهداف/بطاقات). يُدار عبر
 * SchedulerRegistry (كلّ دقيقة)؛ الكادنس الفعليّ للمباراة 45ث عبر next_poll_at. idempotent، آمن عند الفراغ.
 */
class PollLiveFollowedMatchesCommand extends Command
{
    protected $signature = 'follows:poll-live';

    protected $description = 'Poll live followed matches and notify followers of goals/cards.';

    public function handle(PollLiveFollowedMatchesAction $action): int
    {
        $count = $action->handle();

        $this->info("Dispatched {$count} live event notification(s).");

        return self::SUCCESS;
    }
}
