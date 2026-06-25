<?php

declare(strict_types=1);

namespace App\Actions\Admin\Scheduler;

use App\Http\Resources\Admin\Scheduler\ScheduledTaskResource;
use App\Support\Responses\ApiResponse;
use App\Support\Scheduler\SchedulerRegistry;
use Illuminate\Http\JsonResponse;

class ShowScheduledTaskAction
{
    public function handle(string $key): JsonResponse
    {
        if (! SchedulerRegistry::exists($key)) {
            return ApiResponse::error(__('scheduler.not_found'), [], 404);
        }

        return ApiResponse::success(
            data: new ScheduledTaskResource(SchedulerRegistry::state($key))
        );
    }
}
