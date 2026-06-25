<?php

declare(strict_types=1);

namespace App\Actions\Admin\Scheduler;

use App\Http\Resources\Admin\Scheduler\ScheduledTaskResource;
use App\Models\User;
use App\Support\Responses\ApiResponse;
use App\Support\Scheduler\SchedulerRegistry;
use App\Support\Scheduler\SchedulerState;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Throwable;

class RunScheduledTaskAction
{
    /** فترة تهدئة لكل مهمة (ثوانٍ) لمنع التشغيل المتكرّر. */
    private const COOLDOWN = 300;

    public function handle(string $key, User $actor): JsonResponse
    {
        $def = SchedulerRegistry::find($key);

        if ($def === null) {
            return ApiResponse::error(__('scheduler.not_found'), [], 404);
        }

        // قائمة بيضاء صارمة — الأمر يُحَل من السجل لا من إدخال المستخدم
        if (! $def['manual_run_allowed']) {
            return ApiResponse::error(__('scheduler.not_runnable'), [], 403);
        }

        // تهدئة: قفل ذرّي لكل مهمة
        $lockKey = "scheduler:cooldown:{$key}";
        if (! Cache::add($lockKey, true, self::COOLDOWN)) {
            return ApiResponse::error(__('scheduler.cooldown'), [], 429);
        }

        SchedulerState::markRunning($key);
        $startedAt = microtime(true);

        try {
            $exit = Artisan::call($def['command']);
            $output = trim(Artisan::output());
            $success = $exit === 0;

            SchedulerState::record(
                $key,
                $success,
                $startedAt,
                $success ? null : ($output !== '' ? $output : 'exit code '.$exit)
            );
        } catch (Throwable $e) {
            SchedulerState::record($key, false, $startedAt, $e->getMessage());
            $success = false;
        }

        activity('scheduler')
            ->causedBy($actor)
            ->event('manual_run')
            ->withProperties(['key' => $key, 'status' => $success ? 'success' : 'failed'])
            ->log(__('scheduler.activity.manual_run'));

        $task = SchedulerRegistry::state($key)->refresh();

        return $success
            ? ApiResponse::success(__('scheduler.run_success'), new ScheduledTaskResource($task))
            : ApiResponse::error(__('scheduler.run_failed'), [
                'task' => (new ScheduledTaskResource($task))->resolve(),
            ], 422);
    }
}
