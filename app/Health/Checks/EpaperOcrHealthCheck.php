<?php

declare(strict_types=1);

namespace App\Health\Checks;

use App\Enums\EpaperOcrStatus;
use App\Models\Epaper;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

/**
 * مراقبة صحّة استخراج نصّ الأعداد (OCR).
 *  - عالق في processing أطول من الحدّ ⇒ فشل (عامل طابور media معطّل أو مهمة معلّقة).
 *  - عدد الأعداد الفاشلة ≥ العتبة ⇒ تحذير (تراكم؛ التعافي: `epaper:ocr-backfill`).
 * يُعرَض عبر /system/health ويُشغَّل ضمن health:check المجدوَل (فتنطلق الإشعارات).
 */
class EpaperOcrHealthCheck extends Check
{
    public function run(): Result
    {
        $stuckMinutes = max(1, (int) config('epaper.ocr.health.stuck_minutes', 30));
        $failThreshold = max(1, (int) config('epaper.ocr.health.failed_threshold', 10));

        $stuck = Epaper::query()
            ->where('ocr_status', EpaperOcrStatus::Processing->value)
            ->where('updated_at', '<', now()->subMinutes($stuckMinutes))
            ->count();

        $failed = Epaper::query()
            ->where('ocr_status', EpaperOcrStatus::Failed->value)
            ->count();

        $result = Result::make()
            ->meta(['stuck_processing' => $stuck, 'failed_total' => $failed])
            ->shortSummary("{$failed} failed / {$stuck} stuck");

        if ($stuck > 0) {
            return $result->failed(
                "{$stuck} issue(s) stuck in OCR processing for >{$stuckMinutes}m — the media queue worker may be down."
            );
        }

        if ($failed >= $failThreshold) {
            return $result->warning(
                "{$failed} issue(s) failed OCR — run `php artisan epaper:ocr-backfill` to requeue."
            );
        }

        return $result->ok('Epaper OCR healthy.');
    }
}
