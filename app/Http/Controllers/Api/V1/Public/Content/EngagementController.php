<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public\Content;

use App\Enums\EngagementType;
use App\Enums\TrafficChannel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\Content\ReactRequest;
use App\Http\Requests\Public\Content\ViewBeaconRequest;
use App\Support\Engagement\EngageableResolver;
use App\Support\Engagement\EngagementActor;
use App\Support\Engagement\EngagementBeaconToken;
use App\Support\Engagement\EngagementService;
use App\Support\Responses\ApiResponse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * عقد التفاعل العام (Phase 2) — موحّد عبر هدف عام (type + id). يعيد استخدام
 * EngagementService بالكامل. الفاعل هجين (مستخدم مُصادَق أو بصمة زائر).
 *
 * شكل الحالة المُعادة (عقد نظيف، لا تسريب لمخطّط داخلي):
 *   { reaction: 'like'|'dislike'|null, favorited: bool,
 *     metrics: { views, likes, dislikes, favorites } }
 */
class EngagementController extends Controller
{
    public function __construct(private readonly EngagementService $engagement) {}

    public function react(ReactRequest $request, string $type, int $id): JsonResponse
    {
        return $this->withTarget($type, $id, function (Model $target) use ($request): JsonResponse {
            $state = $this->engagement->react(
                $target,
                EngagementActor::fromRequest($request),
                EngagementType::from($request->validated('reaction')),
            );

            return ApiResponse::success(data: $state);
        });
    }

    public function removeReaction(Request $request, string $type, int $id): JsonResponse
    {
        return $this->withTarget($type, $id, function (Model $target) use ($request): JsonResponse {
            return ApiResponse::success(
                data: $this->engagement->removeReaction($target, EngagementActor::fromRequest($request)),
            );
        });
    }

    public function toggleFavorite(Request $request, string $type, int $id): JsonResponse
    {
        return $this->withTarget($type, $id, function (Model $target) use ($request): JsonResponse {
            return ApiResponse::success(
                data: $this->engagement->toggleFavorite($target, EngagementActor::fromRequest($request)),
            );
        });
    }

    public function state(Request $request, string $type, int $id): JsonResponse
    {
        return $this->withTarget($type, $id, function (Model $target) use ($request, $type, $id): JsonResponse {
            // رمز منارة مشاهدة طازج يُصدَر هنا أيضاً: صفحة التفاصيل مُكاشة (CDN/ISR) وقد يتجاوز عمرُها
            // عمرَ التوكن القصير (view_beacon.ttl) فيصبح توكن SSR منتهياً؛ نقطة الحالة غير مُكاشة فيلتقط
            // العميل توكناً حيًّا وقت الترطيب ثمّ يرسل النبضة إلى /view (نفس نمط meta.view_token في التفاصيل).
            return ApiResponse::success(
                data: $this->engagement->stateFor($target, EngagementActor::fromRequest($request)),
                meta: ['view_token' => EngagementBeaconToken::issue($type, $id)],
            );
        });
    }

    /**
     * منارة المشاهدة (uncached) — مصدر احتساب المشاهدات الدقيق خلف الـ CDN.
     *
     * عقد الواجهة العامة:
     *   1) تُعيد نقطة التفاصيل (article/reel/video) رمزاً موقّعاً في meta.view_token.
     *   2) بعد عرض المحتوى (أو بعد مدّة مكوث)، يرسل العميل:
     *        POST /api/v1/engagement/{type}/{id}/view
     *        body:   { "token": "<meta.view_token>" }
     *        header: X-Client-Id: <معرّف عميل ثابت>   (لمنع التكرار + حدّ المعدّل)
     *   3) الاستجابة 200 { accepted: true } بترويسة no-store (لا تُخزَّن أبداً).
     *
     * طبقات مقاومة الإساءة: رمز HMAC مربوط بـ (type,id) ومنتهٍ + هدف منشور فقط +
     * منع تكرار لكل فاعل (30د) + تصفية البوتات + حدّ معدّل لكل عميل. الاحتساب يمرّ
     * عبر EngagementService (مسار التجميع المؤقّت يبقى كما هو).
     */
    public function view(ViewBeaconRequest $request, string $type, int $id): JsonResponse
    {
        return $this->withTarget($type, $id, function (Model $target) use ($request, $type, $id): JsonResponse {
            if (! EngagementBeaconToken::verify((string) $request->validated('token'), $type, $id)) {
                return ApiResponse::error(__('engagement.invalid_token'), [], 422);
            }

            // تصنيف خشن لقناة الزيارة (UTM/المُحيل) — مصدر الزيارات في التحليلات.
            $this->engagement->recordView(
                $target,
                EngagementActor::fromRequest($request),
                TrafficChannel::fromRequest($request)->value,
            );

            return ApiResponse::success(data: ['accepted' => true])
                ->header('Cache-Control', 'no-store, max-age=0');
        });
    }

    /** يتحقّق من النوع ويحمّل الهدف العام، أو يعيد خطأ مناسباً. */
    private function withTarget(string $type, int $id, callable $handler): JsonResponse
    {
        if (! EngageableResolver::isSupported($type)) {
            return ApiResponse::error(__('engagement.unsupported_type'), [], 422);
        }

        $target = EngageableResolver::find($type, $id);
        if ($target === null) {
            return ApiResponse::error(__('engagement.not_found'), [], 404);
        }

        return $handler($target);
    }
}
