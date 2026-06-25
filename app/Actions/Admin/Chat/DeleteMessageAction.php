<?php

declare(strict_types=1);

namespace App\Actions\Admin\Chat;

use App\Models\Message;
use App\Models\User;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * حذف رسالة (ناعم) — صاحبها فقط.
 */
class DeleteMessageAction
{
    public function handle(User $actor, Message $message): JsonResponse
    {
        if ($message->user_id !== $actor->id) {
            return ApiResponse::error(__('chat.not_owner'), [], 403);
        }

        $message->delete();

        return ApiResponse::success(__('chat.deleted'));
    }
}
