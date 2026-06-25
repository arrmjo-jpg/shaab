<?php

declare(strict_types=1);

namespace App\Actions\Admin\Polls;

use App\Http\Resources\Admin\Polls\PollResource;
use App\Models\Poll;
use App\Models\User;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * تبديل تفعيل الاستطلاع — إجراء نشر مستقلّ يتطلّب صلاحية polls.publish (لا polls.edit).
 * هو المسار الوحيد لتغيير is_active (الإنشاء/التعديل لا يمسّانها).
 */
class TogglePollActiveAction
{
    public function handle(Poll $poll, User $actor): JsonResponse
    {
        $poll->update([
            'is_active' => ! $poll->is_active,
            'updated_by' => $actor->id,
        ]);

        return ApiResponse::success(__('polls.poll.status_changed'), new PollResource($poll->fresh('options')));
    }
}
