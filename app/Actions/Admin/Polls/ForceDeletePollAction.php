<?php

declare(strict_types=1);

namespace App\Actions\Admin\Polls;

use App\Models\Poll;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * حذف نهائيّ لاستطلاع — يتسلسل (cascadeOnDelete) إلى الخيارات والأصوات وخيارات الأصوات.
 * لا يمكن استرجاعه.
 */
class ForceDeletePollAction
{
    public function handle(Poll $poll): JsonResponse
    {
        $poll->forceDelete();

        return ApiResponse::success(__('polls.poll.force_deleted'));
    }
}
