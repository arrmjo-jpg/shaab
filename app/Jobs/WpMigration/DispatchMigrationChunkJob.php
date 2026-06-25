<?php

declare(strict_types=1);

namespace App\Jobs\WpMigration;

use App\Enums\MigrationItemStatus;
use App\Enums\MigrationRunStatus;
use App\Models\MigrationItem;
use App\Models\MigrationRun;
use App\Support\WpMigration\MigrationSequence;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * مُوزِّع التنفيذ ذاتيّ الجدولة — دورة واحدة لكل تشغيلة (قواعد #2/#4/#6/#10/#11):
 *
 *  1. استرداد العالق (#4): queued/processing لم يُلمس منذ stale_lock_minutes ⇒ pending
 *     (عامل مات؛ يُعاد توزيعه بأمان لاحقاً).
 *  2. إعادة حساب العدّادات ذرّياً من الدفتر عبر GROUP BY — لا انحراف عن الحقيقة (#10).
 *  3. إن لم تَعُد running ⇒ توقّف عن التوزيع (إيقاف مؤقّت/إيقاف آمن، #6) دون إعادة جدولة.
 *  4. اختر دفعة محدودة (pending أو failed تحت السقف، #2):
 *     - فارغة بلا عناصر طائرة ⇒ التشغيلة مكتملة (+ ختم العدّادات النهائية).
 *     - فارغة مع طائرة ⇒ أعِد جدولة الذات مؤجّلاً (انتظر إنهاء الطائر).
 *     - وإلا: طالِب pending/failed→queued (يمنع إعادة الاختيار) وأطلق مهمة لكل عنصر،
 *       ثم أعِد جدولة الذات مؤجّلاً لمواصلة الخطّ.
 *
 * التفرّد-حتى-المعالجة (ShouldBeUniqueUntilProcessing): القفل يُحرَّر قبل المعالجة
 * فتنجح إعادة الجدولة الذاتية، ويمنع تكدّس موزّعات مؤجّلة لنفس التشغيلة. المهمة تُطلِق
 * فقط ولا تكتب محتوى — كل إبقاء ذرّي داخل ImportWpPostAction (لا قتل وسط معاملة، #7).
 */
class DispatchMigrationChunkJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public int $uniqueFor = 3600;

    public function __construct(private readonly int $runId)
    {
        $this->onQueue((string) config('wp-migration.queue', 'migration'));
    }

    public function uniqueId(): string
    {
        return 'wpmig-dispatch-'.$this->runId;
    }

    public function handle(): void
    {
        $run = MigrationRun::query()->find($this->runId);
        if ($run === null) {
            return;
        }

        $this->reclaimStale($run);
        $this->recomputeCounters($run);

        if ($run->status !== MigrationRunStatus::Running) {
            return; // كفّ عن توزيع عمل جديد؛ الطائر يكمل بأمان (#6)
        }

        $cap = max(1, (int) config('wp-migration.item_tries', 3));
        $chunk = max(1, (int) config('wp-migration.chunk', 200));

        $ids = MigrationItem::query()
            ->where('run_id', $run->id)
            ->where(function ($q) use ($cap): void {
                $q->where('status', MigrationItemStatus::Pending->value)
                    ->orWhere(function ($w) use ($cap): void {
                        $w->where('status', MigrationItemStatus::Failed->value)
                            ->where('attempts', '<', $cap);
                    });
            })
            ->orderBy('id')
            ->limit($chunk)
            ->pluck('id')
            ->map(fn ($x): int => (int) $x)
            ->all();

        if ($ids === []) {
            $this->finishOrWait($run);

            return;
        }

        // مطالبة ذرّية: pending/failed→queued كي لا تُعاد الدفعة في الدورة التالية (#8).
        MigrationItem::query()
            ->where('run_id', $run->id)
            ->whereIn('id', $ids)
            ->whereIn('status', [MigrationItemStatus::Pending->value, MigrationItemStatus::Failed->value])
            ->update(['status' => MigrationItemStatus::Queued->value, 'updated_at' => now()]);

        foreach ($ids as $id) {
            ImportWpPostJob::dispatch($run->id, $id);
        }

        self::dispatch($run->id)->delay(now()->addSeconds(5));
    }

    /** لا عمل متبقٍّ: إمّا انتظر الطائر أو اختم التشغيلة مكتملةً. */
    private function finishOrWait(MigrationRun $run): void
    {
        $inFlight = MigrationItem::query()
            ->where('run_id', $run->id)
            ->whereIn('status', [MigrationItemStatus::Queued->value, MigrationItemStatus::Processing->value])
            ->exists();

        if ($inFlight) {
            self::dispatch($run->id)->delay(now()->addSeconds(10));

            return;
        }

        $run->forceFill([
            'status' => MigrationRunStatus::Completed->value,
            'finished_at' => now(),
            'timeline' => $run->withEvent('completed'),
        ])->save();

        // معرّفات المقالات المُرحَّلة محفوظة (= wp_post_id) — ارفع عدّاد الترقيم فوق
        // أعلى معرّف كي لا يصطدم المحتوى الجديد بالمعرّفات المحجوزة (قاعدة #6).
        MigrationSequence::realign('articles');

        $this->recomputeMediaCounters($run);
    }

    /** يُعيد العناصر العالقة (عامل ميت) إلى pending بعد مهلة القفل (#4). */
    private function reclaimStale(MigrationRun $run): void
    {
        $minutes = max(1, (int) config('wp-migration.stale_lock_minutes', 15));

        MigrationItem::query()
            ->where('run_id', $run->id)
            ->whereIn('status', [MigrationItemStatus::Queued->value, MigrationItemStatus::Processing->value])
            ->where('updated_at', '<', now()->subMinutes($minutes))
            ->update([
                'status' => MigrationItemStatus::Pending->value,
                'last_step' => 'reclaim',
                'updated_at' => now(),
            ]);
    }

    /** عدّادات التقدّم محسوبة ذرّياً من الدفتر (مصدر الحقيقة) — لا تراكم انحرافيّ (#10). */
    private function recomputeCounters(MigrationRun $run): void
    {
        $counts = MigrationItem::query()
            ->where('run_id', $run->id)
            ->selectRaw('status, count(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        $n = fn (MigrationItemStatus $s): int => (int) $counts->get($s->value, 0);

        $done = $n(MigrationItemStatus::Done);
        $partial = $n(MigrationItemStatus::Partial);
        $failed = $n(MigrationItemStatus::Failed);
        $skipped = $n(MigrationItemStatus::Skipped);

        $run->forceFill([
            'done_items' => $done,
            'partial_items' => $partial,
            'failed_items' => $failed,
            'skipped_items' => $skipped,
            'processed_items' => $done + $partial + $failed + $skipped,
        ])->save();
    }

    /**
     * يختم عدّادات الوسائط على مستوى التشغيلة عند الاكتمال — SUM رخيص على أعمدة
     * العنصر (لا مسح JSON). مجاميع حتمية من الدفتر، بلا تراكم انحرافيّ على إعادة المحاولة.
     */
    private function recomputeMediaCounters(MigrationRun $run): void
    {
        $sums = MigrationItem::query()
            ->where('run_id', $run->id)
            ->selectRaw('COALESCE(SUM(media_imported),0) as imported, COALESCE(SUM(media_reused),0) as reused, COALESCE(SUM(media_failed),0) as failed')
            ->first();

        $run->forceFill([
            'media_imported' => (int) ($sums->imported ?? 0),
            'media_reused' => (int) ($sums->reused ?? 0),
            'media_failed' => (int) ($sums->failed ?? 0),
        ])->save();
    }
}
