<?php

declare(strict_types=1);

namespace App\Actions\Admin\Chat;

use App\Enums\ConversationType;
use App\Http\Resources\Admin\Chat\ConversationResource;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\User;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * قائمة محادثات الفاعل (+ آخر رسالة + عدّاد غير مقروء). تضمن عضوية الغرفة العامة
 * كسلاً (firstOrCreate) فيراها كل أدمن دائماً. عدّاد غير المقروء عبر استعلام واحد
 * مُجمَّع (join على pivot الفاعل) — لا N+1.
 */
class ListConversationsAction
{
    public function handle(User $actor): JsonResponse
    {
        $this->ensureGeneralMembership($actor);

        $conversations = Conversation::query()
            ->forUser($actor->id)
            ->with(['participants.user:id,name,avatar', 'latestMessage'])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->get();

        $unread = $this->unreadMap($actor, $conversations->pluck('id')->all());
        $conversations->each(fn (Conversation $c) => $c->setAttribute('unread_count', $unread[$c->id] ?? 0));

        return ApiResponse::success(data: ConversationResource::collection($conversations)->resolve());
    }

    /** ضمان وجود الغرفة العامة وعضوية الفاعل فيها (كسلاً، آمن للتكرار). */
    private function ensureGeneralMembership(User $actor): void
    {
        $general = Conversation::firstOrCreate(
            ['type' => ConversationType::General->value],
            ['created_by' => $actor->id],
        );

        ConversationParticipant::firstOrCreate(
            ['conversation_id' => $general->id, 'user_id' => $actor->id],
        );
    }

    /**
     * خريطة عدّاد غير المقروء لكل محادثة — استعلام واحد: رسائل الآخرين الأحدث من
     * علامة قراءة الفاعل (أو الكلّ إن لم يقرأ بعد).
     *
     * @param  array<int,int>  $conversationIds
     * @return array<int,int>
     */
    private function unreadMap(User $actor, array $conversationIds): array
    {
        if ($conversationIds === []) {
            return [];
        }

        return Message::query()
            ->join('conversation_participants as p', function ($join) use ($actor): void {
                $join->on('p.conversation_id', '=', 'messages.conversation_id')
                    ->where('p.user_id', '=', $actor->id);
            })
            ->whereIn('messages.conversation_id', $conversationIds)
            ->where('messages.user_id', '!=', $actor->id)
            ->where(function ($q): void {
                $q->whereNull('p.last_read_at')
                    ->orWhereColumn('messages.created_at', '>', 'p.last_read_at');
            })
            ->groupBy('messages.conversation_id')
            ->selectRaw('messages.conversation_id, COUNT(*) as aggregate')
            ->pluck('aggregate', 'messages.conversation_id')
            ->map(fn ($v): int => (int) $v)
            ->all();
    }
}
