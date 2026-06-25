<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\PullMediaToLocalJob;
use App\Models\MediaAsset;
use Illuminate\Console\Command;

/**
 * توطين الأصول البعيدة فقط (legacy remote-only) إلى التخزين المحلّي canonical —
 * استمرارية الأعمال وإزالة الاعتماد على البعيد فقط تدريجياً.
 *
 * يُجدوِل PullMediaToLocalJob لكل أصل (بثّ، idempotent، تبديل ذرّي للمحلّي).
 * resumable (stored_local=false يُستبعَد بعد التوطين) · دفعات · آمن للطابور.
 *
 * ملاحظة: الهجرة الأولى الضخمة (WordPress) تُنقَل يدوياً — هذا الأمر لاستعادة
 * التشغيل/الاستمرارية للأصول الموجودة في الجدول، مع حدّ لكل تشغيل.
 */
class RepairRemoteMediaCommand extends Command
{
    protected $signature = 'media:repair:remote {--pull : وطّن الأصول البعيدة فقط إلى المحلّي} {--limit=500 : أقصى عدد أصول لكل تشغيل}';

    protected $description = 'Localize remote-only assets back to canonical local storage (--pull).';

    public function handle(): int
    {
        if (! $this->option('pull')) {
            $this->error('Only --pull is supported. Run: media:repair:remote --pull');

            return self::FAILURE;
        }

        $limit = max(1, (int) $this->option('limit'));
        $dispatched = 0;

        MediaAsset::query()
            ->library()
            ->where('stored_remote', true)
            ->where('stored_local', false)
            ->where('kind', '!=', 'external')
            ->orderBy('id')
            ->chunkById(100, function ($chunk) use (&$dispatched, $limit): bool {
                foreach ($chunk as $asset) {
                    if ($dispatched >= $limit) {
                        return false;
                    }
                    PullMediaToLocalJob::dispatch($asset->id);
                    $dispatched++;
                }

                return true;
            });

        $this->info("Dispatched {$dispatched} pull job(s) to localize remote-only assets.");

        return self::SUCCESS;
    }
}
