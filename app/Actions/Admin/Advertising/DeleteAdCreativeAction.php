<?php

declare(strict_types=1);

namespace App\Actions\Admin\Advertising;

use App\Models\AdCreative;
use App\Support\Advertising\AdServingInvalidator;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * حذف ناعم لإبداع. الإسنادات تبقى (لا cascade على الحذف الناعم)، لكن علاقة الإبداع تستبعد
 * المحذوف ناعماً فلا يظهر في بِركة المرشّحين. الإبطال بعد الحذف يُسقط البِرَك المتأثّرة فوراً.
 */
class DeleteAdCreativeAction
{
    public function handle(AdCreative $creative): JsonResponse
    {
        $creative->delete();

        AdServingInvalidator::forCreative($creative);

        return ApiResponse::success(__('ads.creative.deleted'));
    }
}
