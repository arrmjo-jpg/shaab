<?php

declare(strict_types=1);

namespace App\Actions\Public\Polls;

use App\Enums\PollAudienceMode;
use App\Http\Resources\Public\Polls\PollResultsResource;
use App\Models\Poll;
use App\Models\PollOption;
use App\Models\PollVote;
use App\Support\Engagement\EngagementActor;
use App\Support\Polls\PollVoter;
use App\Support\Responses\ApiResponse;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * تصويت عام (POST /api/v1/polls/{uuid}/vote). يفرض: الفتح، الجمهور، العدد المسموح،
 * انتماء الخيارات، تصفية البوت، ومنع التكرار. الكتابة معاملاتية: بطاقة (PollVote) +
 * خيارات (PollVoteOption) + زيادة ذرّية لـ votes_count. منع التكرار: مسار سريع NX
 * (تحسين) + قيد فرادة (poll_id, voter_hash) في القاعدة (الضمانة). لا تُخزَّن (no-store).
 */
final class CastVoteAction
{
    /** @param  array<int,int>  $optionIds */
    public function handle(string $uuid, array $optionIds, Request $request): JsonResponse
    {
        $poll = Poll::query()->where('uuid', $uuid)->with('options')->first();
        if ($poll === null) {
            return $this->noStore(ApiResponse::error(__('polls.public.not_found'), [], 404));
        }

        $optionIds = array_values(array_unique(array_map('intval', $optionIds)));
        $actor = EngagementActor::fromRequest($request);

        if (! $poll->isOpenForVoting()) {
            return $this->noStore(ApiResponse::error(__('polls.public.closed'), [], 422));
        }
        if ($poll->audience_mode === PollAudienceMode::Authenticated && $actor->userId === null) {
            return $this->noStore(ApiResponse::error(__('polls.public.not_authenticated'), [], 403));
        }
        if (! $poll->allow_multiple && count($optionIds) !== 1) {
            return $this->noStore(ApiResponse::error(__('polls.public.single_only'), [], 422));
        }

        $validIds = $poll->options->pluck('id')->map(fn ($id): int => (int) $id)->all();
        if (array_diff($optionIds, $validIds) !== []) {
            return $this->noStore(ApiResponse::error(__('polls.public.invalid_options'), [], 422));
        }

        // تصفية البوت: لا تسجيل ولا تسريب (استجابة طبيعية).
        if ($actor->isBot) {
            return $this->accepted($poll, hasVoted: false);
        }

        $voterHash = PollVoter::hash($actor, $request);

        // مسار سريع NX (تحسين) — قيد الفرادة في القاعدة هو الضمانة الموثوقة.
        if ((bool) config('polls.dedup.fast_cache', true)
            && ! Cache::add('poll:vote:'.$poll->id.':'.$voterHash, true, now()->addDay())) {
            return $this->alreadyVoted($poll);
        }

        try {
            DB::transaction(function () use ($poll, $optionIds, $voterHash): void {
                $ballot = PollVote::create([
                    'poll_id' => $poll->id,
                    'voter_hash' => $voterHash,
                    'created_at' => now(),
                ]);

                DB::table('poll_vote_options')->insert(array_map(
                    static fn (int $optionId): array => [
                        'poll_vote_id' => $ballot->id,
                        'poll_option_id' => $optionId,
                    ],
                    $optionIds,
                ));

                // زيادة ذرّية للعدّاد (عدّاد أداء — مُستثنى من التدقيق، مرآة AdCounter).
                PollOption::query()->whereIn('id', $optionIds)->increment('votes_count');
            });
        } catch (QueryException $exception) {
            // 23000 = انتهاك الفرادة ⇒ صوّت مسبقاً (سباق متزامن أو انتهت صلاحية كاش الـ NX).
            if ((string) ($exception->errorInfo[0] ?? '') === '23000') {
                return $this->alreadyVoted($poll);
            }
            throw $exception;
        }

        $poll->load('options'); // عدّادات محدّثة

        return $this->accepted($poll, hasVoted: true);
    }

    private function accepted(Poll $poll, bool $hasVoted): JsonResponse
    {
        return $this->noStore(ApiResponse::success(__('polls.public.accepted'), [
            'accepted' => true,
            'already_voted' => false,
            'results' => $this->results($poll, $hasVoted),
        ]));
    }

    private function alreadyVoted(Poll $poll): JsonResponse
    {
        return $this->noStore(ApiResponse::success(__('polls.public.already_voted'), [
            'accepted' => false,
            'already_voted' => true,
            'results' => $this->results($poll, hasVoted: true),
        ]));
    }

    /** @return array<string,mixed>|null */
    private function results(Poll $poll, bool $hasVoted): ?array
    {
        return $poll->resultsVisibleTo($hasVoted)
            ? (new PollResultsResource($poll->loadMissing('options')))->resolve()
            : null;
    }

    private function noStore(JsonResponse $response): JsonResponse
    {
        return $response->header('Cache-Control', 'no-store, max-age=0');
    }
}
