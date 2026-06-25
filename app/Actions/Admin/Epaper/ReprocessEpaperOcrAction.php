<?php

declare(strict_types=1);

namespace App\Actions\Admin\Epaper;

use App\Http\Resources\Admin\Epaper\EpaperResource;
use App\Jobs\ExtractEpaperTextJob;
use App\Models\Epaper;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * إعادة تشغيل استخراج نصّ العدد (rerun) يدوياً من لوحة الإدارة — يضبط pending
 * ويُعيد جدولة الوظيفة. مفيد بعد تهيئة مزوّد OCR أو لاسترجاع حالة failed.
 */
class ReprocessEpaperOcrAction
{
    public function handle(Epaper $epaper): JsonResponse
    {
        ExtractEpaperTextJob::enqueue($epaper);

        return ApiResponse::success(
            __('epaper.ocr.requeued'),
            new EpaperResource($epaper->fresh()->load(['mediaAsset', 'author'])),
        );
    }
}
