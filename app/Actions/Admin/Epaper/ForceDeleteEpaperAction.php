<?php

declare(strict_types=1);

namespace App\Actions\Admin\Epaper;

use App\Models\Epaper;
use App\Support\Epaper\EpaperSearchIndexer;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * حذف نهائيّ لعدد — تتسلسل النسخ وتاريخ المسارات (FK cascade). الوسائط لا تُحذف
 * (مشتركة/مُزالة التكرار؛ تنظيف اليتيم عبر مهمة الوسائط الدورية).
 */
class ForceDeleteEpaperAction
{
    public function handle(Epaper $epaper): JsonResponse
    {
        $epaperId = $epaper->id; // التُقِط قبل الحذف؛ الوظيفة لن تجده ⇒ إزالة من الفهرس
        $epaper->forceDelete();

        EpaperSearchIndexer::queueSync($epaperId);

        return ApiResponse::success(__('epaper.force_deleted'));
    }
}
