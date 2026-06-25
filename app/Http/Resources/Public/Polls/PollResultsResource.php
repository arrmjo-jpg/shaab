<?php

declare(strict_types=1);

namespace App\Http\Resources\Public\Polls;

use App\Models\Poll;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Poll
 *
 * نتائج الاستطلاع: إجماليّ الأصوات (مجموع الاختيارات) ونسبة كل خيار من الإجمالي. متعدّد
 * الاختيار: الإجمالي = مجموع الاختيارات (لا عدد البطاقات)، فالنسبة حصّة الخيار من الاختيارات.
 * يتطلّب تحميل options. يُحسَب من votes_count (العدّاد) — مصدر الحقيقة poll_vote_options.
 */
class PollResultsResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $total = (int) $this->options->sum('votes_count');

        return [
            'total_votes' => $total,
            'options' => $this->options
                ->map(fn ($option): array => [
                    'id' => $option->id,
                    'votes_count' => (int) $option->votes_count,
                    'percentage' => $total > 0 ? round($option->votes_count / $total * 100, 2) : 0,
                ])
                ->values()
                ->all(),
        ];
    }
}
