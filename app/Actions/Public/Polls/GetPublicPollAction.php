<?php

declare(strict_types=1);

namespace App\Actions\Public\Polls;

use App\Http\Resources\Public\Polls\PollResultsResource;
use App\Http\Resources\Public\Polls\PublicPollResource;
use App\Models\Poll;
use App\Support\Engagement\EngagementActor;
use App\Support\Polls\PollVoter;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * تهيئة الودجة العامة (GET /api/v1/polls/{uuid}). per-actor (يحلّ has_voted ورؤية
 * النتائج للفاعل) ⇒ no-store. لا SSR per-actor — الشِل مُكاش على الحافة ويُهيّأ من هنا.
 */
final class GetPublicPollAction
{
    public function handle(string $uuid, Request $request): JsonResponse
    {
        $poll = Poll::query()->where('uuid', $uuid)->with('options')->first();

        if ($poll === null) {
            return ApiResponse::error(__('polls.public.not_found'), [], 404)
                ->header('Cache-Control', 'no-store, max-age=0');
        }

        $actor = EngagementActor::fromRequest($request);
        $hasVoted = $poll->votes()
            ->where('voter_hash', PollVoter::hash($actor, $request))
            ->exists();

        $resource = new PublicPollResource($poll);
        $resource->hasVoted = $hasVoted;
        $resource->results = $poll->resultsVisibleTo($hasVoted)
            ? (new PollResultsResource($poll))->resolve()
            : null;

        return ApiResponse::success(data: $resource)
            ->header('Cache-Control', 'no-store, max-age=0');
    }
}
