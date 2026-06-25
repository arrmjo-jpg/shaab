<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Scheduler;

use App\Actions\Admin\Scheduler\ListScheduledTasksAction;
use App\Actions\Admin\Scheduler\RunScheduledTaskAction;
use App\Actions\Admin\Scheduler\ShowScheduledTaskAction;
use App\Actions\Admin\Scheduler\UpdateScheduledTaskAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Scheduler\UpdateScheduledTaskRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SchedulerController extends Controller
{
    public function index(): JsonResponse
    {
        return (new ListScheduledTasksAction)->handle();
    }

    public function show(string $task): JsonResponse
    {
        return (new ShowScheduledTaskAction)->handle($task);
    }

    public function update(UpdateScheduledTaskRequest $request, string $task): JsonResponse
    {
        return (new UpdateScheduledTaskAction)->handle(
            $task,
            $request->validated(),
            $request->user()
        );
    }

    public function run(Request $request, string $task): JsonResponse
    {
        return (new RunScheduledTaskAction)->handle($task, $request->user());
    }
}
