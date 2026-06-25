<?php

declare(strict_types=1);

namespace App\Actions\Admin\Polls;

use App\Models\Poll;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * حذف ناعم لاستطلاع (سلّة). يبقى قابلاً للاسترجاع؛ خياراته وأصواته تبقى (لا cascade على
 * الحذف الناعم) فيُسترجَع كاملاً.
 */
class DeletePollAction
{
    public function handle(Poll $poll): JsonResponse
    {
        $poll->delete();

        return ApiResponse::success(__('polls.poll.deleted'));
    }
}
