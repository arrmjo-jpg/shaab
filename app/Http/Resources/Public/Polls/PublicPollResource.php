<?php

declare(strict_types=1);

namespace App\Http\Resources\Public\Polls;

use App\Models\Poll;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Poll
 *
 * تهيئة الودجة العامة: التعريف (سؤال + خيارات بلا عدّادات) + حالة لحظية (is_open) +
 * حالة الفاعل (has_voted) + النتائج (فقط حين تكون مرئيّة للفاعل). الحقول الخاصّة بالفاعل
 * تُحقَن من الـ Action (per-actor، لذا الاستجابة no-store).
 */
class PublicPollResource extends JsonResource
{
    public bool $hasVoted = false;

    /** @var array<string, mixed>|null */
    public ?array $results = null;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'question' => $this->question,
            'allow_multiple' => (bool) $this->allow_multiple,
            'audience_mode' => $this->audience_mode?->value,
            'result_visibility' => $this->result_visibility?->value,
            'is_open' => $this->isOpenForVoting(),
            'has_voted' => $this->hasVoted,
            'options' => $this->options
                ->map(fn ($option): array => ['id' => $option->id, 'label' => $option->label])
                ->values()
                ->all(),
            'results' => $this->results,
        ];
    }
}
