<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public\Media;

use App\Actions\Admin\Media\StoreMediaAssetAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Media\StoreMediaAssetRequest;
use App\Http\Resources\Admin\Media\MediaAssetResource;
use App\Models\MediaAsset;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * وسائط الكاتب (Writer Media Ownership Layer) — رفع + استطلاع حالة المعالجة.
 *
 * يعيد استخدام نفس خطّ الإدارة حرفيّاً (بلا مسار موازٍ، بلا منطق وسائط جديد):
 *  - الرفع   : StoreMediaAssetAction (نفس dedupe/التخزين/تجدول TranscodeVideoAssetJob).
 *  - التحقّق : StoreMediaAssetRequest (نفس قيود MIME/الحجم/الأبعاد/ملف reel — تنعكس تلقائيّاً).
 *  - الاستجابة: MediaAssetResource (نفس تقدّم المعالجة الحبيبيّ).
 *
 * الملكيّة: StoreMediaAssetAction يضبط uploaded_by = الكاتب الحالي. والاطّلاع على الحالة
 * محصور بأصول الكاتب نفسه (حارس IDOR: 404 لغير المالك حتى لا تُكشَف ملكيّة الآخرين).
 */
class WriterMediaController extends Controller
{
    public function store(StoreMediaAssetRequest $request): JsonResponse
    {
        $asset = (new StoreMediaAssetAction)->handle(
            $request->file('file'),
            $request->user(),
            $request->input('profile'),
            // الكاتب: dedupe ضمن أصوله فقط — فالأصل المُعاد مملوكٌ له دائماً (حارس IDOR).
            dedupeWithinActor: true,
        );

        return ApiResponse::success(__('media.asset_uploaded'), new MediaAssetResource($asset), 201);
    }

    public function show(Request $request, MediaAsset $mediaAsset): JsonResponse
    {
        abort_unless($mediaAsset->uploaded_by === $request->user()->id, 404);

        return ApiResponse::success(data: new MediaAssetResource($mediaAsset));
    }
}
