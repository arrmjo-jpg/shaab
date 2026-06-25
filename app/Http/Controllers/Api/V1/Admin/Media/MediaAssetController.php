<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Media;

use App\Actions\Admin\Media\DeleteMediaAssetAction;
use App\Actions\Admin\Media\ListMediaAssetsAction;
use App\Actions\Admin\Media\RegenerateMediaDerivativesAction;
use App\Actions\Admin\Media\ReprocessMediaAssetAction;
use App\Actions\Admin\Media\StoreExternalVideoAction;
use App\Actions\Admin\Media\StoreMediaAssetAction;
use App\Actions\Admin\Media\UpdateMediaAssetAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Media\ListMediaAssetsRequest;
use App\Http\Requests\Admin\Media\ResolveExternalVideoRequest;
use App\Http\Requests\Admin\Media\StoreExternalVideoRequest;
use App\Http\Requests\Admin\Media\StoreMediaAssetRequest;
use App\Http\Requests\Admin\Media\UpdateMediaAssetRequest;
use App\Http\Resources\Admin\Media\MediaAssetResource;
use App\Models\MediaAsset;
use App\Support\Media\ExternalVideoResolver;
use App\Support\Media\MediaUsage;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MediaAssetController extends Controller
{
    public function index(ListMediaAssetsRequest $request): JsonResponse
    {
        return (new ListMediaAssetsAction)->handle($request->validated());
    }

    /** تفصيل أصل (بالـ uuid) — يشمل «أين يُستخدَم»؛ ويُستخدَم لاستطلاع حالة الفيديو. */
    public function show(MediaAsset $mediaAsset): JsonResponse
    {
        $mediaAsset->load([
            'articles:id,title,type',
            'liveUpdates:id,article_id',
            'liveUpdates.article:id,title',
        ])->loadCount(MediaUsage::countSelectors());

        return ApiResponse::success(data: new MediaAssetResource($mediaAsset));
    }

    /** تعديل البيانات الوصفية (alt/caption/credit/source) دون إعادة رفع. */
    public function update(UpdateMediaAssetRequest $request, MediaAsset $mediaAsset): JsonResponse
    {
        $asset = (new UpdateMediaAssetAction)->handle($mediaAsset, $request->validated());

        return ApiResponse::success(__('media.metadata_updated'), new MediaAssetResource($asset));
    }

    /** حذف أصل مع حارس استخدام (يُحظَر إن كان مستخدَماً ما لم يُمرَّر force). */
    public function destroy(Request $request, MediaAsset $mediaAsset): JsonResponse
    {
        $result = (new DeleteMediaAssetAction)->handle($mediaAsset, $request->boolean('force'));

        if (! $result['deleted']) {
            return ApiResponse::error(
                __('media.in_use', ['count' => $result['usage_count']]),
                ['usage_count' => $result['usage_count']],
                409,
            );
        }

        return ApiResponse::success(__('media.deleted'));
    }

    /** إعادة توليد مشتقّات الصور لكل المكتبة (بعد تغيير العلامة المائية). */
    public function regenerateDerivatives(): JsonResponse
    {
        $count = (new RegenerateMediaDerivativesAction)->handle();

        return ApiResponse::success(
            __('media.derivatives_queued', ['count' => $count]),
            ['queued' => $count],
        );
    }

    /** إعادة معالجة أصل مفرد (retry للحالة failed). */
    public function reprocess(MediaAsset $mediaAsset): JsonResponse
    {
        if (! (new ReprocessMediaAssetAction)->handle($mediaAsset)) {
            return ApiResponse::error(__('media.not_processable'), [], 422);
        }

        return ApiResponse::success(
            __('media.reprocess_queued'),
            new MediaAssetResource($mediaAsset->fresh()),
        );
    }

    public function store(StoreMediaAssetRequest $request): JsonResponse
    {
        $asset = (new StoreMediaAssetAction)->handle(
            $request->file('file'),
            $request->user(),
            $request->input('profile'),
        );

        return ApiResponse::success(
            __('media.asset_uploaded'),
            new MediaAssetResource($asset),
            201,
        );
    }

    /** معاينة: يكشف المزوّد ورابط التضمين دون حفظ (paste detection). */
    public function resolveExternal(ResolveExternalVideoRequest $request): JsonResponse
    {
        $resolved = ExternalVideoResolver::resolve($request->validated('url'));

        if ($resolved === null) {
            return ApiResponse::error(__('media.external.unsupported'), [], 422);
        }

        return ApiResponse::success(data: $resolved);
    }

    /** إنشاء فيديو خارجي كأصل مكتبة مركزي. */
    public function storeExternal(StoreExternalVideoRequest $request): JsonResponse
    {
        $asset = (new StoreExternalVideoAction)->handle($request->validated('url'), $request->user());

        return ApiResponse::success(
            __('media.external.added'),
            new MediaAssetResource($asset),
            201,
        );
    }
}
