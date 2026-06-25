<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Admin\Vertix\ImportVertixNewsBatchAction;
use App\Enums\VertixPhase;
use App\Enums\VertixRunStatus;
use App\Models\Category;
use App\Models\VertixRun;
use App\Support\Vertix\VertixSource;
use Illuminate\Console\Command;

/**
 * استيراد أخبار Vertix الجديدة (Incremental) — متزامن، بلا حاجة لعامل طابور.
 * يستأنف من العلامة المائيّة؛ --reset يبدأ من الصفر (آمن: Idempotent بمفتاح newsid).
 */
class ImportVertixNewsCommand extends Command
{
    protected $signature = 'vertix:import-news {--reset : ابدأ من الصفر (العلامة = 0)}';

    protected $description = 'استيراد أخبار Vertix الجديدة فقط إلى AlphaCMS (Incremental, Idempotent).';

    public function handle(): int
    {
        if (! Category::query()->exists()) {
            $this->error('استورد الأقسام أولاً: php artisan vertix:import-categories أو من لوحة الإدارة.');

            return self::FAILURE;
        }

        $run = VertixRun::forPhase(VertixPhase::News);
        if ($this->option('reset')) {
            $run->forceFill(['high_water' => 0, 'cursor' => 0, 'backfill_done' => false, 'imported' => 0, 'failed' => 0])->save();
        }

        ImportVertixNewsBatchAction::initialize($run);

        $total = VertixSource::make()->newsCount();
        $run->forceFill([
            'status' => VertixRunStatus::Running->value,
            'total' => $total,
            'started_at' => $run->started_at ?? now(),
            'finished_at' => null,
            'last_error' => null,
        ])->save();

        $this->info("الاستيراد بترتيب الأحدث ← الأقدم. الإجمالي المؤهَّل: {$total}");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $chunk = max(1, (int) config('vertix.chunk', 500));
        $action = new ImportVertixNewsBatchAction;

        do {
            $run->refresh();
            $result = $action->handleRun($run, $chunk);
            $bar->advance($result['imported'] + $result['skipped']);
            if ($result['done']) {
                break;
            }
        } while (true);

        $bar->finish();
        $this->newLine();

        $run->refresh()->forceFill(['status' => VertixRunStatus::Completed->value, 'finished_at' => now()])->save();
        $this->info("تمّ. مُستورَد: {$run->fresh()->imported}، فاشل: {$run->fresh()->failed}");

        return self::SUCCESS;
    }
}
