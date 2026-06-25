<?php

declare(strict_types=1);

namespace App\Actions\Admin\Advertising;

use App\Models\AdCreative;
use App\Support\Advertising\AdServingInvalidator;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * حذف نهائيّ لإبداع — يتسلسل (cascadeOnDelete) إلى إسناداته. لذا يُحلّ الإبطال ويُفرَّغ قبل
 * الحذف بينما الإسنادات قائمة (بعده تختفي بالتسلسل فيتعذّر استنتاج مساحاتها). لا استرجاع.
 */
class ForceDeleteAdCreativeAction
{
    public function handle(AdCreative $creative): JsonResponse
    {
        AdServingInvalidator::forCreative($creative);

        $creative->forceDelete();

        return ApiResponse::success(__('ads.creative.force_deleted'));
    }
}
