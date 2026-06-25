<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public\Polls;

use App\Actions\Public\Polls\CastVoteAction;
use App\Actions\Public\Polls\GetPollResultsAction;
use App\Actions\Public\Polls\GetPublicPollAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\Polls\CastVoteRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * السطح العام للاستطلاعات — تحكّم رفيع (يستدعي الـ Actions فقط). العنونة بالـ uuid (غير
 * قابل للتعداد). التهيئة/النتائج قراءة؛ التصويت كتابة محدودة المعدّل (throttle:poll.vote).
 */
class PollController extends Controller
{
    public function show(Request $request, string $uuid): JsonResponse
    {
        return (new GetPublicPollAction)->handle($uuid, $request);
    }

    public function vote(CastVoteRequest $request, string $uuid): JsonResponse
    {
        return (new CastVoteAction)->handle($uuid, $request->validated()['option_ids'], $request);
    }

    public function results(Request $request, string $uuid): JsonResponse
    {
        return (new GetPollResultsAction)->handle($uuid, $request);
    }
}
