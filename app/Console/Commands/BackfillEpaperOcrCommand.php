<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\EpaperOcrStatus;
use App\Jobs\ExtractEpaperTextJob;
use App\Models\Epaper;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * إعادة جدولة استخراج نصّ الأعداد (OCR) بالدُّفعات. الافتراضيّ: الأعداد بلا حالة
 * أو الفاشلة/المعلّقة فقط. الخنق عبر الطابور نفسه (تُجدوَل على queue=media فيعالجها
 * العامل بوتيرته) + chunk + حدّ اختياري لتقييد دفعة الجدولة.
 */
class BackfillEpaperOcrCommand extends Command
{
    protected $signature = 'epaper:ocr-backfill
        {--limit=0 : حدّ أقصى لعدد الأعداد المُجدوَلة (0 = الكل)}
        {--force : يشمل المكتملة (done/partial) لإعادة استخراجها أيضاً}';

    protected $description = 'يُعيد جدولة استخراج نصّ الأعداد (OCR) بالدُّفعات.';

    public function handle(): int
    {
        $force = (bool) $this->option('force');
        $limit = max(0, (int) $this->option('limit'));
        $chunk = max(1, (int) config('epaper.ocr.backfill.chunk', 50));

        $query = Epaper::query()->whereNotNull('media_asset_id');

        if (! $force) {
            $query->where(function (Builder $q): void {
                $q->whereNull('ocr_status')
                    ->orWhereIn('ocr_status', [
                        EpaperOcrStatus::Failed->value,
                        EpaperOcrStatus::Pending->value,
                    ]);
            });
        }

        $dispatched = 0;
        $query->orderBy('id')->chunkById($chunk, function ($epapers) use (&$dispatched, $limit): bool {
            foreach ($epapers as $epaper) {
                if ($limit > 0 && $dispatched >= $limit) {
                    return false; // بلغنا الحدّ — أوقِف
                }
                ExtractEpaperTextJob::enqueue($epaper);
                $dispatched++;
            }

            return true;
        });

        $this->info("Queued OCR extraction for {$dispatched} issue(s).");

        return self::SUCCESS;
    }
}
