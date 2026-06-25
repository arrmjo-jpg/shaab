<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Engagement\ViewBuffer;
use Illuminate\Console\Command;

/**
 * يفرّغ زيادات المشاهدات المُجمَّعة إلى عدّادات قاعدة البيانات — يُدار عبر
 * SchedulerRegistry (everyMinute). idempotent وآمن عند الفراغ/عدم الدعم.
 */
class FlushViewBufferCommand extends Command
{
    protected $signature = 'engagement:flush-views';

    protected $description = 'Flush buffered view increments into engagement counters (coalesced, contention-free).';

    public function handle(): int
    {
        $count = ViewBuffer::flush();

        $this->info("Flushed view buffers for {$count} target(s).");

        return self::SUCCESS;
    }
}
