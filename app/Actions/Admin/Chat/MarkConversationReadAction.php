<?php

declare(strict_types=1);

namespace App\Actions\Admin\Chat;

use App\Models\Conversation;
use App\Models\User;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * تعليم المحادثة مقروءة للفاعل (last_read_at = now). بوابة العضوية أولاً.
 */
class MarkConversationReadAction
{
    public function handle(User $actor, Conversation $conversation): JsonResponse
    {
        if (! ChatAccess::isParticipant($conversation, $actor)) {
            return ApiResponse::error(__('chat.forbidden'), [], 403);
        }

        $conversation->participants()
            ->where('user_id', $actor->id)
            ->update(['last_read_at' => now()]);

        return ApiResponse::success(__('chat.marked_read'));
    }
}
