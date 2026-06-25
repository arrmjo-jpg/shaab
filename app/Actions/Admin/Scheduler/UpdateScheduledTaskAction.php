<?php

declare(strict_types=1);

namespace App\Actions\Admin\Scheduler;

use App\Http\Resources\Admin\Scheduler\ScheduledTaskResource;
use App\Models\User;
use App\Support\Responses\ApiResponse;
use App\Support\Scheduler\SchedulerRegistry;
use Illuminate\Http\JsonResponse;

class UpdateScheduledTaskAction
{
    public function handle(string $key, array $validated, User $actor): JsonResponse
    {
        if (! SchedulerRegistry::exists($key)) {
            return ApiResponse::error(__('scheduler.not_found'), [], 404);
        }

        $task = SchedulerRegistry::state($key);

        // فقط enabled + notes — لا تعبير ولا أمر (code-authoritative)
        if (array_key_exists('enabled', $validated)) {
            $task->enabled = (bool) $validated['enabled'];
        }
        if (array_key_exists('notes', $validated)) {
            $task->notes = $validated['notes'];
        }
        $task->save();

        // تدقيق صريح (لا trait — مفتاح نصّي): نيّة المدير فقط
        activity('scheduler')
            ->causedBy($actor)
            ->event('updated')
            ->withProperties([
                'key' => $key,
                'enabled' => $task->enabled,
                'notes' => $task->notes,
            ])
            ->log(__('scheduler.activity.updated'));

        return ApiResponse::success(
            __('scheduler.updated'),
            new ScheduledTaskResource($task->refresh())
        );
    }
}
