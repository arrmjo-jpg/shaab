<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Broadcast;

use App\Actions\Admin\Broadcast\ArchiveBroadcastAction;
use App\Actions\Admin\Broadcast\EndBroadcastAction;
use App\Actions\Admin\Broadcast\FailBroadcastAction;
use App\Actions\Admin\Broadcast\MarkBroadcastOfflineAction;
use App\Actions\Admin\Broadcast\ResumeBroadcastAction;
use App\Actions\Admin\Broadcast\ScheduleBroadcastAction;
use App\Actions\Admin\Broadcast\StartBroadcastAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Broadcast\ScheduleBroadcastRequest;
use App\Models\Broadcast;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * انتقالات دورة حياة البثّ — نقاط مخصّصة صريحة (لا setStatus عام). كل نقطة تستدعي
 * فعلها المخصّص الذي يفرض شرعية الانتقال عبر آلة الحالة. الحالة لا تُمَسّ عبر CRUD.
 */
class BroadcastLifecycleController extends Controller
{
    public function schedule(ScheduleBroadcastRequest $request, Broadcast $broadcast): JsonResponse
    {
        return (new ScheduleBroadcastAction)->handle($broadcast, $request->validated('scheduled_at'), $request->user());
    }

    public function start(Request $request, Broadcast $broadcast): JsonResponse
    {
        return (new StartBroadcastAction)->handle($broadcast, $request->user());
    }

    public function offline(Request $request, Broadcast $broadcast): JsonResponse
    {
        return (new MarkBroadcastOfflineAction)->handle($broadcast, $request->user());
    }

    public function resume(Request $request, Broadcast $broadcast): JsonResponse
    {
        return (new ResumeBroadcastAction)->handle($broadcast, $request->user());
    }

    public function end(Request $request, Broadcast $broadcast): JsonResponse
    {
        return (new EndBroadcastAction)->handle($broadcast, $request->user());
    }

    public function fail(Request $request, Broadcast $broadcast): JsonResponse
    {
        return (new FailBroadcastAction)->handle($broadcast, $request->string('reason')->value() ?: null, $request->user());
    }

    public function archive(Request $request, Broadcast $broadcast): JsonResponse
    {
        return (new ArchiveBroadcastAction)->handle($broadcast, $request->user());
    }
}
