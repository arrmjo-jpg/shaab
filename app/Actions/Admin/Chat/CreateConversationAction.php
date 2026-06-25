<?php

declare(strict_types=1);

namespace App\Actions\Admin\Chat;

use App\Enums\ConversationType;
use App\Http\Resources\Admin\Chat\ConversationResource;
use App\Models\Conversation;
use App\Models\User;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * إنشاء محادثة: مباشرة (1↔1، مع منع تكرار عبر dm_key) أو مجموعة. الفاعل عضو دائماً.
 */
class CreateConversationAction
{
    public function handle(User $actor, array $validated): JsonResponse
    {
        $type = $validated['type'];
        $userIds = array_values(array_unique(array_map('intval', $validated['user_ids'])));

        if ($type === ConversationType::Direct->value) {
            return $this->direct($actor, $userIds);
        }

        return $this->group($actor, $userIds, $validated['title']);
    }

    /** مباشرة: طرف آخر واحد، مع dedup عبر dm_key (قيد فريد كحارس نهائي). */
    private function direct(User $actor, array $userIds): JsonResponse
    {
        $other = $userIds[0] ?? null;
        if ($other === null || $other === $actor->id || count($userIds) !== 1) {
            return ApiResponse::error(__('chat.invalid_direct'), [], 422);
        }

        $key = Conversation::dmKey($actor->id, $other);

        $existing = Conversation::where('dm_key', $key)->first();
        if ($existing !== null) {
            return ApiResponse::success(
                __('chat.conversation_ready'),
                new ConversationResource($existing->load('participants.user:id,name,avatar', 'latestMessage')),
            );
        }

        $conversation = DB::transaction(function () use ($actor, $other, $key): Conversation {
            $c = Conversation::create([
                'type' => ConversationType::Direct->value,
                'dm_key' => $key,
                'created_by' => $actor->id,
            ]);
            $c->participants()->create(['user_id' => $actor->id]);
            $c->participants()->create(['user_id' => $other]);

            return $c;
        });

        return ApiResponse::success(
            __('chat.conversation_created'),
            new ConversationResource($conversation->load('participants.user:id,name,avatar', 'latestMessage')),
            201,
        );
    }

    /** مجموعة: عنوان + أعضاء (الفاعل دائماً ضمنهم). */
    private function group(User $actor, array $userIds, ?string $title): JsonResponse
    {
        $members = array_values(array_unique(array_merge([$actor->id], $userIds)));

        $conversation = DB::transaction(function () use ($actor, $members, $title): Conversation {
            $c = Conversation::create([
                'type' => ConversationType::Group->value,
                'title' => $title,
                'created_by' => $actor->id,
            ]);
            foreach ($members as $id) {
                $c->participants()->create(['user_id' => $id]);
            }

            return $c;
        });

        return ApiResponse::success(
            __('chat.conversation_created'),
            new ConversationResource($conversation->load('participants.user:id,name,avatar', 'latestMessage')),
            201,
        );
    }
}
