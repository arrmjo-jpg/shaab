<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Chat;

use App\Enums\ConversationType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * مورد المحادثة — العنوان المعروض مُحتسَب (للمباشرة = اسم الطرف الآخر). unread_count
 * يُمرَّر مُحتسَباً مسبقاً من الـ Action (يُحقَن كسمة) لتفادي N+1.
 */
class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $actorId = $request->user()?->id;

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'type' => $this->type->value,
            'title' => $this->displayTitle($actorId),
            'participants' => $this->whenLoaded('participants', fn () => $this->participants
                ->map(fn ($p) => [
                    'id' => $p->user?->id,
                    'name' => $p->user?->name,
                    'avatar' => $p->user?->avatar,
                ])->values()),
            'last_message' => $this->whenLoaded('latestMessage', fn () => $this->latestMessage ? [
                'id' => $this->latestMessage->id,
                'body' => $this->latestMessage->body,
                'sender_id' => $this->latestMessage->user_id,
                'created_at' => $this->latestMessage->created_at?->toISOString(),
            ] : null),
            'unread_count' => (int) ($this->unread_count ?? 0),
            'last_message_at' => $this->last_message_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }

    /** العنوان المعروض: المباشرة = اسم الطرف الآخر؛ العامة = نصّ ثابت؛ المجموعة = title. */
    private function displayTitle(?int $actorId): string
    {
        if ($this->type === ConversationType::General) {
            return __('chat.general_room');
        }

        if ($this->type === ConversationType::Direct && $this->relationLoaded('participants')) {
            $other = $this->participants->first(fn ($p) => $p->user_id !== $actorId);

            return (string) ($other?->user?->name ?? __('chat.direct'));
        }

        return (string) ($this->title ?? __('chat.group'));
    }
}
