<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public\Content;

use App\Actions\Admin\Content\CreateReelAction;
use App\Actions\Admin\Content\TransitionReelStatusAction;
use App\Actions\Public\Content\ListMyReelsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\Content\PublicStoreReelRequest;
use App\Http\Requests\Public\Content\PublicSubmitReelRequest;
use App\Models\Reel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * إرسال ريل من الكاتب (نطاق عام — V1).
 *
 * موصِّل رفيع: يفوّض لـ CreateReelAction/TransitionReelStatusAction القائمين دون
 * أي منطق أعمال. كل التفويض/الإسناد الذاتي/حصر الحالة يُفرَض داخل الـ Actions و
 * ReelAuthorizationGuard/ReelWorkflowGuard (مصدر الحقيقة). الحقول المحدودة في
 * PublicStoreReelRequest (بلا media_asset_id — V1).
 */
class WriterReelController extends Controller
{
    public function store(PublicStoreReelRequest $request): JsonResponse
    {
        return (new CreateReelAction)->handle(
            $request->validated(),
            $request->user()
        );
    }

    public function submit(PublicSubmitReelRequest $request, Reel $reel): JsonResponse
    {
        return (new TransitionReelStatusAction)->handle(
            $reel,
            $request->validated(),
            $request->user()
        );
    }

    public function mine(Request $request): JsonResponse
    {
        return (new ListMyReelsAction)->handle(
            $request->user(),
            $request
        );
    }
}
