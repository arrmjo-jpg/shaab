<?php

declare(strict_types=1);

namespace App\Actions\Admin\Polls;

use App\Http\Resources\Admin\Polls\PollResource;
use App\Models\Poll;
use App\Models\User;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * إنشاء استطلاع. يُنشأ معطّلاً دائماً (is_active=false) — التفعيل إجراء نشر مستقلّ
 * (polls.publish)، لا عبر الإنشاء. نسبة الإنشاء/التعديل تُسجَّل (created_by/updated_by).
 */
class CreatePollAction
{
    /** @param  array<string, mixed>  $data */
    public function handle(array $data, User $actor): JsonResponse
    {
        $options = $this->normalizeOptions($data['options'] ?? []);
        unset($data['options']);

        $data['is_active'] = false;
        $data['created_by'] = $actor->id;
        $data['updated_by'] = $actor->id;

        $poll = DB::transaction(function () use ($data, $options): Poll {
            $poll = Poll::create($data);

            foreach ($options as $i => $option) {
                $poll->options()->create([
                    'label' => $option['label'],
                    'sort_order' => $option['sort_order'] ?? $i,
                    'votes_count' => 0,
                ]);
            }

            return $poll;
        });

        return ApiResponse::success(__('polls.poll.created'), new PollResource($poll->fresh('options')), 201);
    }

    /**
     * @param  array<int, array<string, mixed>>  $options
     * @return array<int, array{label:string, sort_order:?int}>
     */
    private function normalizeOptions(array $options): array
    {
        return collect($options)
            ->map(fn (array $row): array => [
                'label' => trim((string) ($row['label'] ?? '')),
                'sort_order' => isset($row['sort_order']) ? (int) $row['sort_order'] : null,
            ])
            ->filter(fn (array $row): bool => $row['label'] !== '')
            ->values()
            ->all();
    }
}
