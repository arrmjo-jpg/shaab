<?php

declare(strict_types=1);

namespace App\Actions\Admin\Polls;

use App\Models\Poll;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * استرجاع استطلاع محذوف ناعماً من السلّة.
 */
class RestorePollAction
{
    public function handle(Poll $poll): JsonResponse
    {
        $poll->restore();

        return ApiResponse::success(__('polls.poll.restored'));
    }
}
