<?php

declare(strict_types=1);

namespace App\Actions\Admin\Scheduler;

use App\Http\Resources\Admin\Scheduler\ScheduledTaskResource;
use App\Models\ScheduledTask;
use App\Support\Responses\ApiResponse;
use App\Support\Scheduler\SchedulerRegistry;
use Illuminate\Http\JsonResponse;

class ListScheduledTasksAction
{
    public function handle(): JsonResponse
    {
        // تأكيد وجود صف لكل مهمة في السجل (كسول، مفعّل افتراضياً)
        $keys = array_keys(SchedulerRegistry::all());
        foreach ($keys as $key) {
            SchedulerRegistry::state($key);
        }

        $tasks = ScheduledTask::whereIn('key', $keys)
            ->get()
            ->sortBy(fn (ScheduledTask $t) => array_search($t->key, $keys, true))
            ->values();

        return ApiResponse::success(
            data: ScheduledTaskResource::collection($tasks)->resolve()
        );
    }
}
