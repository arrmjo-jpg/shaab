<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Polls;

use App\Actions\Admin\Polls\CreatePollAction;
use App\Actions\Admin\Polls\DeletePollAction;
use App\Actions\Admin\Polls\ForceDeletePollAction;
use App\Actions\Admin\Polls\ListPollsAction;
use App\Actions\Admin\Polls\PollAnalyticsAction;
use App\Actions\Admin\Polls\PollEntityAnalyticsAction;
use App\Actions\Admin\Polls\RestorePollAction;
use App\Actions\Admin\Polls\TogglePollActiveAction;
use App\Actions\Admin\Polls\UpdatePollAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Polls\StorePollRequest;
use App\Http\Requests\Admin\Polls\UpdatePollRequest;
use App\Http\Resources\Admin\Polls\PollResource;
use App\Models\Poll;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * إدارة الاستطلاعات — تحكّم رفيع (يستدعي الـ Actions فقط). التفعيل (active) عبر مسار نشر
 * مستقلّ ببوابة polls.publish. التفويض عبر permission middleware على المسارات.
 */
class PollController extends Controller
{
    public function index(): JsonResponse
    {
        return (new ListPollsAction)->handle();
    }

    /** تحليلات الأسطول (مؤشّرات + حالة + متصدّرون + مشاركة حديثة). */
    public function analytics(): JsonResponse
    {
        return (new PollAnalyticsAction)->handle();
    }

    /** تحليلات استطلاع مفرد (مشاركة + توزيع + اتجاه) — نطاق زمني عبر range/from/to. */
    public function entityAnalytics(Request $request, Poll $poll): JsonResponse
    {
        return (new PollEntityAnalyticsAction)->handle(
            $poll,
            $request->query('range'),
            $request->query('from'),
            $request->query('to'),
        );
    }

    public function show(Poll $poll): JsonResponse
    {
        return ApiResponse::success(data: new PollResource($poll->load('options')));
    }

    public function store(StorePollRequest $request): JsonResponse
    {
        return (new CreatePollAction)->handle($request->validated(), $request->user());
    }

    public function update(UpdatePollRequest $request, Poll $poll): JsonResponse
    {
        return (new UpdatePollAction)->handle($poll, $request->validated(), $request->user());
    }

    public function toggleActive(Request $request, Poll $poll): JsonResponse
    {
        return (new TogglePollActiveAction)->handle($poll, $request->user());
    }

    public function destroy(Poll $poll): JsonResponse
    {
        return (new DeletePollAction)->handle($poll);
    }

    public function restore(Poll $poll): JsonResponse
    {
        return (new RestorePollAction)->handle($poll);
    }

    public function forceDelete(Poll $poll): JsonResponse
    {
        return (new ForceDeletePollAction)->handle($poll);
    }
}
