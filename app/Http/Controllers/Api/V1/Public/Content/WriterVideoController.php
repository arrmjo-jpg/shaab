<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public\Content;

use App\Actions\Admin\VideoLibrary\CreateVideoAction;
use App\Actions\Admin\VideoLibrary\TransitionVideoStatusAction;
use App\Actions\Public\Content\ListMyVideosAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\Content\PublicStoreVideoRequest;
use App\Http\Requests\Public\Content\PublicSubmitVideoRequest;
use App\Models\Video;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * إرسال فيديو من الكاتب (نطاق عام — V1).
 *
 * موصِّل رفيع: يفوّض لـ CreateVideoAction/TransitionVideoStatusAction القائمين دون
 * أي منطق أعمال. كل التفويض/الإسناد الذاتي/حصر الحالة يُفرَض داخل الـ Actions و
 * VideoAuthorizationGuard/VideoWorkflowGuard (مصدر الحقيقة). الحقول المحدودة في
 * PublicStoreVideoRequest (بلا media_asset_id/source_url — V1).
 */
class WriterVideoController extends Controller
{
    public function store(PublicStoreVideoRequest $request): JsonResponse
    {
        return (new CreateVideoAction)->handle(
            $request->validated(),
            $request->user()
        );
    }

    public function submit(PublicSubmitVideoRequest $request, Video $video): JsonResponse
    {
        return (new TransitionVideoStatusAction)->handle(
            $video,
            $request->validated(),
            $request->user()
        );
    }

    public function mine(Request $request): JsonResponse
    {
        return (new ListMyVideosAction)->handle(
            $request->user(),
            $request
        );
    }
}
