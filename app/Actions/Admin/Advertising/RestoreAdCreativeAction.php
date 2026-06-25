<?php

declare(strict_types=1);

namespace App\Actions\Admin\Advertising;

use App\Models\AdCreative;
use App\Support\Advertising\AdServingInvalidator;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * استرجاع إبداع محذوف ناعماً. قد يعود مرشّحاً للعرض (إن كان نشطاً ضمن حملة قابلة للعرض)
 * ⇒ إبطال دفاعيّ للمساحات التي يظهر فيها كي تُعاد البناء بالحالة الجديدة.
 */
class RestoreAdCreativeAction
{
    public function handle(AdCreative $creative): JsonResponse
    {
        $creative->restore();

        AdServingInvalidator::forCreative($creative);

        return ApiResponse::success(__('ads.creative.restored'));
    }
}
