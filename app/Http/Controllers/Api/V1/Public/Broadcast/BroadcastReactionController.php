<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public\Broadcast;

use App\Enums\EngagementType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\Broadcast\BroadcastReactionRequest;
use App\Models\Broadcast;
use App\Models\User;
use App\Support\Broadcast\BroadcastReactionGuard;
use App\Support\Engagement\EngagementActor;
use App\Support\Engagement\EngagementService;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * تفاعل البثّ (B7) — like/dislike للمستخدمين المُصادَقين فقط (الزوّار يُرفَضون عبر
 * middleware المصادقة). يُعيد استخدام محرّك التفاعل الموحّد (EngagementService) للتخزين
 * المتعدّد الأشكال والعدّادات الآمنة-للتسابق (لا جدول موازٍ). يطبّق حارس البثّ:
 * الرؤية (publiclyVisible) + الإشراف (B6: مغلق/محظور). الفاعل دائماً مستخدم ("u{id}")
 * — لا هوية زائر هنا.
 *
 * العقد المُعاد (نظيف، خاصّ بالبثّ): { reaction: 'like'|'dislike'|null,
 * metrics: { likes, dislikes } } — بلا favorited/views (ليست مفاهيم تفاعل بثّ).
 */
class BroadcastReactionController extends Controller
{
    public function __construct(private readonly EngagementService $engagement) {}

    /** حالة تفاعل المستخدم الحالي + العدّادات (قراءة — رؤية فقط). */
    public function show(Request $request, Broadcast $broadcast): JsonResponse
    {
        if (! BroadcastReactionGuard::visible($broadcast)) {
            return ApiResponse::error(__('broadcast.not_found'), [], 404);
        }

        return $this->state($broadcast, $request->user());
    }

    /** ضبط/تبديل تفاعل (like/dislike). تفاعل أحادي حصري — يستبدل المعاكس، ويُلغي المماثل. */
    public function store(BroadcastReactionRequest $request, Broadcast $broadcast): JsonResponse
    {
        $user = $request->user();
        if ($denied = BroadcastReactionGuard::check($broadcast, EngagementActor::user((int) $user->id)->key())) {
            return $denied;
        }

        $this->engagement->react(
            $broadcast,
            EngagementActor::user((int) $user->id),
            EngagementType::from($request->validated('reaction')),
        );

        return $this->state($broadcast, $user);
    }

    /** إزالة تفاعل المستخدم. */
    public function destroy(Request $request, Broadcast $broadcast): JsonResponse
    {
        $user = $request->user();
        if ($denied = BroadcastReactionGuard::check($broadcast, EngagementActor::user((int) $user->id)->key())) {
            return $denied;
        }

        $this->engagement->removeReaction($broadcast, EngagementActor::user((int) $user->id));

        return $this->state($broadcast, $user);
    }

    private function state(Broadcast $broadcast, User $user): JsonResponse
    {
        $state = $this->engagement->stateFor($broadcast, EngagementActor::user((int) $user->id));

        return ApiResponse::success(data: [
            'reaction' => $state['reaction'],
            'metrics' => [
                'likes' => $state['metrics']['likes'],
                'dislikes' => $state['metrics']['dislikes'],
            ],
        ]);
    }
}
