<?php

declare(strict_types=1);

namespace App\Actions\Admin\Chat;

use App\Http\Resources\Admin\Chat\MessageResource;
use App\Models\Message;
use App\Models\User;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * تعديل رسالة — صاحبها فقط. يضبط edited_at. body نصّ صِرف.
 */
class UpdateMessageAction
{
    public function handle(User $actor, Message $message, array $validated): JsonResponse
    {
        if ($message->user_id !== $actor->id) {
            return ApiResponse::error(__('chat.not_owner'), [], 403);
        }

        $message->forceFill([
            'body' => trim($validated['body']),
            'edited_at' => now(),
        ])->save();

        return ApiResponse::success(
            __('chat.updated'),
            new MessageResource($message->fresh()->load('sender:id,name,avatar', 'attachment')),
        );
    }
}
