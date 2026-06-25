<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Advertising\AdEventBuffer;
use Illuminate\Console\Command;

/**
 * يفرّغ أحداث الإعلان المُجمَّعة (انطباعات/نقرات) إلى العدّادات + التجميع اليوميّ —
 * يُدار عبر SchedulerRegistry (everyMinute). idempotent وآمن عند الفراغ/عدم الدعم.
 */
class FlushAdEventsCommand extends Command
{
    protected $signature = 'ads:flush-events';

    protected $description = 'Flush buffered ad impression/click events into counters and daily stats (coalesced).';

    public function handle(): int
    {
        $count = AdEventBuffer::flush();

        $this->info("Flushed ad events for {$count} placement(s).");

        return self::SUCCESS;
    }
}
