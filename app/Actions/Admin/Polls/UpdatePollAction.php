<?php

declare(strict_types=1);

namespace App\Actions\Admin\Polls;

use App\Http\Resources\Admin\Polls\PollResource;
use App\Models\Poll;
use App\Models\PollOption;
use App\Models\User;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * تعديل استطلاع + مزامنة خياراته. is_active لا يُمسّ هنا (التفعيل عبر polls.publish).
 * سلامة الحذف (القرار A): يُمنع حذف أيّ خيار يملك أصواتاً — يُرفض التعديل كاملاً دون
 * تغيير. تمرّ كتابات الخيارات عبر نماذج Eloquent (تدقيق صحيح، لا تجاوز للأحداث).
 */
class UpdatePollAction
{
    /** @param  array<string, mixed>  $data */
    public function handle(Poll $poll, array $data, User $actor): JsonResponse
    {
        $options = $this->normalizeOptions($data['options'] ?? []);
        unset($data['options'], $data['is_active']);

        $incomingIds = collect($options)
            ->pluck('id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->all();

        // القرار A: رفض إزالة خيار يملك أصواتاً (حقيقة لا تُمحى).
        $blocked = $poll->options()
            ->when($incomingIds !== [], fn ($q) => $q->whereNotIn('id', $incomingIds))
            ->whereHas('votes')
            ->exists();

        if ($blocked) {
            return ApiResponse::error(__('polls.option.has_votes'), [], 422);
        }

        $data['updated_by'] = $actor->id;

        DB::transaction(function () use ($poll, $data, $options, $incomingIds): void {
            $poll->update($data);

            // حذف الخيارات المُزالة (غير المُصوَّتة) — عبر نماذج كي تُطلَق أحداث التدقيق.
            $poll->options()
                ->when($incomingIds !== [], fn ($q) => $q->whereNotIn('id', $incomingIds))
                ->get()
                ->each(fn (PollOption $option) => $option->delete());

            $existing = $poll->options()->get()->keyBy('id');

            foreach (array_values($options) as $i => $option) {
                $id = $option['id'] ?? null;

                if ($id !== null && $existing->has((int) $id)) {
                    $existing->get((int) $id)->update([
                        'label' => $option['label'],
                        'sort_order' => $option['sort_order'] ?? $i,
                    ]);

                    continue;
                }

                $poll->options()->create([
                    'label' => $option['label'],
                    'sort_order' => $option['sort_order'] ?? $i,
                    'votes_count' => 0,
                ]);
            }
        });

        return ApiResponse::success(__('polls.poll.updated'), new PollResource($poll->fresh('options')));
    }

    /**
     * @param  array<int, array<string, mixed>>  $options
     * @return array<int, array{id:?int, label:string, sort_order:?int}>
     */
    private function normalizeOptions(array $options): array
    {
        return collect($options)
            ->map(fn (array $row): array => [
                'id' => isset($row['id']) && $row['id'] !== '' ? (int) $row['id'] : null,
                'label' => trim((string) ($row['label'] ?? '')),
                'sort_order' => isset($row['sort_order']) ? (int) $row['sort_order'] : null,
            ])
            ->filter(fn (array $row): bool => $row['label'] !== '')
            ->values()
            ->all();
    }
}
