<?php

declare(strict_types=1);

namespace App\Support\WpMigration;

use App\Enums\MigrationItemStatus;
use App\Enums\MigrationRunStatus;
use App\Models\MigrationItem;
use App\Models\MigrationRun;

/**
 * مراقبة حيّة + تقرير ختام لتشغيلة (للقراءة فقط). يُحسب كل شيء من الدفتر:
 *  - العدّ لكل حالة عبر GROUP BY واحد رخيص (مفهرس بـ run_id,status).
 *  - عدّادات الوسائط عبر SUM واحد على أعمدة العنصر.
 *  - الأداء (المنقضي/الإنتاجية/الوقت المتبقّي) مشتقّ من الطوابع والمعالَج.
 * التقرير يضيف توزيع أسباب الفشل (تجميع نادر عند الاكتمال، لا في حلقة الاستطلاع).
 */
final class MigrationStats
{
    public function __construct(private readonly MigrationRun $run) {}

    public static function for(MigrationRun $run): self
    {
        return new self($run);
    }

    /** لقطة حيّة للوحة (تُستطلَع أثناء التشغيل). */
    public function build(): array
    {
        $counts = $this->counts();
        $total = array_sum($counts);
        $processed = $counts['done'] + $counts['partial'] + $counts['failed'] + $counts['skipped'];
        $media = $this->media();

        return [
            'status' => $this->run->status->value,
            'counts' => ['total' => $total] + $counts,
            'performance' => $this->performance($total, $processed),
            'media' => $media,
            'timeline' => $this->run->timeline ?? [],
            'started_at' => $this->run->started_at?->toISOString(),
            'finished_at' => $this->run->finished_at?->toISOString(),
        ];
    }

    /** ملخّص ختام واضح (#7) — يشمل توزيع أسباب الفشل ومعدّل النجاح والمدّة. */
    public function report(): array
    {
        $counts = $this->counts();
        $total = array_sum($counts);
        $succeeded = $counts['done'] + $counts['partial'];
        $processed = $succeeded + $counts['failed'] + $counts['skipped'];

        return [
            'status' => $this->run->status->value,
            'is_complete' => $this->run->status->isTerminal(),
            'counts' => ['total' => $total] + $counts,
            'succeeded' => $succeeded,
            'processed' => $processed,
            'success_rate' => $total > 0 ? round($succeeded / $total * 100, 1) : 0.0,
            'duration_seconds' => $this->elapsedSeconds(),
            'media' => $this->media(),
            'failures' => $this->failuresByReason(),
            'timeline' => $this->run->timeline ?? [],
            'started_at' => $this->run->started_at?->toISOString(),
            'finished_at' => $this->run->finished_at?->toISOString(),
        ];
    }

    /** @return array{pending:int,queued:int,processing:int,done:int,partial:int,failed:int,skipped:int} */
    private function counts(): array
    {
        $rows = MigrationItem::query()
            ->where('run_id', $this->run->id)
            ->selectRaw('status, count(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        $n = fn (MigrationItemStatus $s): int => (int) $rows->get($s->value, 0);

        return [
            'pending' => $n(MigrationItemStatus::Pending),
            'queued' => $n(MigrationItemStatus::Queued),
            'processing' => $n(MigrationItemStatus::Processing),
            'done' => $n(MigrationItemStatus::Done),
            'partial' => $n(MigrationItemStatus::Partial),
            'failed' => $n(MigrationItemStatus::Failed),
            'skipped' => $n(MigrationItemStatus::Skipped),
        ];
    }

    /** @return array{imported:int,reused:int,failed:int} */
    private function media(): array
    {
        $sums = MigrationItem::query()
            ->where('run_id', $this->run->id)
            ->selectRaw('COALESCE(SUM(media_imported),0) as imported, COALESCE(SUM(media_reused),0) as reused, COALESCE(SUM(media_failed),0) as failed')
            ->first();

        return [
            'imported' => (int) ($sums->imported ?? 0),
            'reused' => (int) ($sums->reused ?? 0),
            'failed' => (int) ($sums->failed ?? 0),
        ];
    }

    private function performance(int $total, int $processed): array
    {
        $elapsed = $this->elapsedSeconds();
        $remaining = max(0, $total - $processed);

        $throughput = ($elapsed > 0 && $processed > 0)
            ? round($processed / ($elapsed / 60), 2)
            : 0.0;

        // ETA فقط أثناء التشغيل وبوجود معدّل قابل للاستقراء وعمل متبقٍّ.
        $eta = ($this->run->status === MigrationRunStatus::Running && $processed > 0 && $elapsed > 0 && $remaining > 0)
            ? (int) round($remaining * $elapsed / $processed)
            : null;

        return [
            'elapsed_seconds' => $elapsed,
            'throughput_per_min' => $throughput,
            'eta_seconds' => $eta,
            'percent' => $total > 0 ? round($processed / $total * 100, 1) : 0.0,
        ];
    }

    /** ثوانٍ منذ البدء حتى الانتهاء (أو الآن إن جارية) — طوابع خام تفادياً لالتباس Carbon. */
    private function elapsedSeconds(): int
    {
        $started = $this->run->started_at;
        if ($started === null) {
            return 0;
        }
        $end = $this->run->finished_at ?? now();

        return max(0, $end->getTimestamp() - $started->getTimestamp());
    }

    /**
     * توزيع أسباب الفشل/التخطّي (failed + skipped) — تجميع PHP مُقطَّع (الفشل أقلّية
     * في ترحيل ناجح؛ والتقرير يُعرَض عند الاكتمال لا في حلقة الاستطلاع).
     *
     * @return array<int,array{reason:string,count:int}>
     */
    private function failuresByReason(): array
    {
        $byReason = [];

        MigrationItem::query()
            ->where('run_id', $this->run->id)
            ->whereIn('status', [MigrationItemStatus::Failed->value, MigrationItemStatus::Skipped->value])
            ->orderBy('id')
            ->chunk(1000, function ($items) use (&$byReason): void {
                foreach ($items as $item) {
                    $reason = (string) data_get($item->flags, 'reason', 'unknown');
                    $byReason[$reason] = ($byReason[$reason] ?? 0) + 1;
                }
            });

        arsort($byReason);

        return array_map(
            fn (string $reason, int $count): array => ['reason' => $reason, 'count' => $count],
            array_keys($byReason),
            array_values($byReason),
        );
    }
}
