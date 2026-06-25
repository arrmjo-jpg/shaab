<?php

declare(strict_types=1);

namespace App\Actions\Admin\Chat;

use App\Http\Resources\Admin\Chat\MessageResource;
use App\Models\Conversation;
use App\Models\User;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * تاريخ رسائل محادثة (الأحدث أولاً) بترقيم مؤشّر (?before=id). بوابة العضوية أولاً.
 */
class ListMessagesAction
{
    private const PER_PAGE = 30;

    public function handle(User $actor, Conversation $conversation, Request $request): JsonResponse
    {
        if (! ChatAccess::isParticipant($conversation, $actor)) {
            return ApiResponse::error(__('chat.forbidden'), [], 403);
        }

        // withTrashed: المحذوفة تظهر كـ tombstone في الخيط (المورد يُخفي محتواها).
        // باقي الاستعلامات (آخر رسالة/غير المقروء) تبقى على النطاق الافتراضي فتتجاهلها.
        $query = $conversation->messages()
            ->withTrashed()
            ->with(['sender:id,name,avatar', 'attachment'])
            ->orderByDesc('id');

        $before = (int) $request->integer('before', 0);
        if ($before > 0) {
            $query->where('id', '<', $before);
        }

        $messages = $query->limit(self::PER_PAGE)->get();
        $nextBefore = $messages->count() === self::PER_PAGE ? $messages->last()->id : null;

        return ApiResponse::success(
            data: MessageResource::collection($messages)->resolve(),
            meta: ['next_before' => $nextBefore],
        );
    }
}
