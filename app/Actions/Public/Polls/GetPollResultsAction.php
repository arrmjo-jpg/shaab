<?php

declare(strict_types=1);

namespace App\Actions\Public\Polls;

use App\Enums\PollResultVisibility;
use App\Http\Resources\Public\Polls\PollResultsResource;
use App\Models\Poll;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * النتائج العامة (GET /api/v1/polls/{uuid}/results) — رؤية عامّة فقط (غير per-actor)
 * فهي قابلة للكاش على الحافة: always، أو after_close بعد الإغلاق. after_vote لا يُكشف هنا
 * أبداً (per-actor — يأتي عبر التهيئة/التصويت). مدّة كاش قصيرة (قريب-الحيّ، صديق CDN).
 */
final class GetPollResultsAction
{
    public function handle(string $uuid, Request $request): JsonResponse
    {
        $poll = Poll::query()->where('uuid', $uuid)->with('options')->first();

        if ($poll === null) {
            return ApiResponse::error(__('polls.public.not_found'), [], 404);
        }

        $visible = $poll->result_visibility === PollResultVisibility::Always
            || ($poll->result_visibility === PollResultVisibility::AfterClose && $poll->isClosed());

        $data = [
            'visible' => $visible,
            'results' => $visible ? (new PollResultsResource($poll))->resolve() : null,
        ];

        return ApiResponse::success(data: $data)
            ->header('Cache-Control', 'public, max-age=15');
    }
}
