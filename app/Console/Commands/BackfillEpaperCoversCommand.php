<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\GenerateEpaperCoverJob;
use App\Models\Epaper;
use Illuminate\Console\Command;

/**
 * توليد الأغلفة للأعداد القائمة التي لا غلاف لها بعد (دفعات). يُجدوِل
 * GenerateEpaperCoverJob لكل عدد له PDF وبلا conversions['cover'].
 *
 * ⚠️ يُكتب ولا يُشغَّل تلقائياً — يُشغّله المالك يدوياً: php artisan epaper:backfill-covers
 */
class BackfillEpaperCoversCommand extends Command
{
    protected $signature = 'epaper:backfill-covers {--chunk=50 : حجم الدفعة}';

    protected $description = 'Queue cover generation for existing epapers that have no cover yet.';

    public function handle(): int
    {
        $chunk = max(1, (int) $this->option('chunk'));
        $queued = 0;

        Epaper::query()
            ->whereNotNull('media_asset_id')
            ->with('mediaAsset:id,conversions')
            ->chunkById($chunk, function ($epapers) use (&$queued): void {
                foreach ($epapers as $epaper) {
                    $hasCover = isset($epaper->mediaAsset?->conversions['cover']['path']);
                    if ($hasCover) {
                        continue;
                    }
                    GenerateEpaperCoverJob::enqueue($epaper);
                    $queued++;
                }
            });

        $this->info("Queued cover generation for {$queued} epaper(s).");

        return self::SUCCESS;
    }
}
