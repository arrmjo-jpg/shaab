<?php

declare(strict_types=1);

namespace App\Jobs\Vertix;

use App\Actions\Admin\Vertix\ImportVertixNewsBatchAction;
use App\Enums\VertixPhase;
use App\Enums\VertixRunStatus;
use App\Models\VertixRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * مُعالِج أخبار Vertix ذاتيّ الجدولة — مستقلّ تماماً عن مهام WordPress (طابور vertix).
 * يعالج دفعة فوق العلامة المائيّة ثمّ يعيد جدولة نفسه حتى تنفد الأخبار الجديدة.
 * التفرّد-حتى-المعالجة يمنع تكدّس مُعالِجات متوازية لنفس المرحلة.
 */
class ImportVertixNewsChunkJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 600;

    public int $uniqueFor = 3600;

    public function __construct()
    {
        $this->onQueue((string) config('vertix.queue', 'vertix'));
    }

    public function uniqueId(): string
    {
        return 'vertix-news-chunk';
    }

    public function handle(): void
    {
        $run = VertixRun::forPhase(VertixPhase::News);
        if ($run->status !== VertixRunStatus::Running) {
            return; // أُوقِفت/اكتملت ⇒ لا عمل جديد
        }

        $chunk = max(1, (int) config('vertix.chunk', 500));
        // handleRun يُحدّث حالة المرحلة (high_water/cursor/imported) داخلياً.
        $result = (new ImportVertixNewsBatchAction)->handleRun($run, $chunk);

        if ($result['done']) {
            $run->forceFill([
                'status' => VertixRunStatus::Completed->value,
                'finished_at' => now(),
            ])->save();

            return;
        }

        self::dispatch()->delay(now()->addSeconds(2));
    }
}
