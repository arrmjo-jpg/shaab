<?php

declare(strict_types=1);

use App\Models\Poll;
use App\Models\PollOption;
use App\Models\PollVote;
use App\Models\PollVoteOption;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::flush());

/** استطلاع منشور مع خيارات. */
function votingPoll(array $overrides = [], int $options = 2): Poll
{
    $poll = Poll::factory()->create(array_merge(['is_active' => true], $overrides));
    PollOption::factory()->count($options)->create(['poll_id' => $poll->id]);

    return $poll->load('options');
}

it('records a single-choice vote and increments the counter atomically', function (): void {
    $poll = votingPoll();
    $opt = $poll->options->first();

    $res = $this->withHeaders(['X-Client-Id' => 'c1'])
        ->postJson("/api/v1/polls/{$poll->uuid}/vote", ['option_ids' => [$opt->id]]);

    $res->assertOk();
    expect($res->json('data.accepted'))->toBeTrue()
        ->and((int) PollOption::whereKey($opt->id)->value('votes_count'))->toBe(1)
        ->and(PollVote::where('poll_id', $poll->id)->count())->toBe(1)
        ->and(PollVoteOption::where('poll_option_id', $opt->id)->count())->toBe(1);
});

it('prevents duplicate votes from the same client (fast-cache path)', function (): void {
    $poll = votingPoll();
    $opt = $poll->options->first();

    $this->withHeaders(['X-Client-Id' => 'c1'])->postJson("/api/v1/polls/{$poll->uuid}/vote", ['option_ids' => [$opt->id]])->assertOk();
    $second = $this->withHeaders(['X-Client-Id' => 'c1'])->postJson("/api/v1/polls/{$poll->uuid}/vote", ['option_ids' => [$opt->id]]);

    $second->assertOk();
    expect($second->json('data.already_voted'))->toBeTrue()
        ->and(PollVote::where('poll_id', $poll->id)->count())->toBe(1);
});

it('prevents duplicate votes via the DB unique guarantee when fast-cache is off', function (): void {
    config(['polls.dedup.fast_cache' => false]);
    $poll = votingPoll();
    $opt = $poll->options->first();

    $this->withHeaders(['X-Client-Id' => 'c1'])->postJson("/api/v1/polls/{$poll->uuid}/vote", ['option_ids' => [$opt->id]])->assertOk();
    $second = $this->withHeaders(['X-Client-Id' => 'c1'])->postJson("/api/v1/polls/{$poll->uuid}/vote", ['option_ids' => [$opt->id]]);

    $second->assertOk();
    expect($second->json('data.already_voted'))->toBeTrue()
        ->and(PollVote::where('poll_id', $poll->id)->count())->toBe(1);
});

it('counts distinct clients separately (tier A)', function (): void {
    $poll = votingPoll();
    $opt = $poll->options->first();

    $this->withHeaders(['X-Client-Id' => 'c1'])->postJson("/api/v1/polls/{$poll->uuid}/vote", ['option_ids' => [$opt->id]])->assertOk();
    $this->withHeaders(['X-Client-Id' => 'c2'])->postJson("/api/v1/polls/{$poll->uuid}/vote", ['option_ids' => [$opt->id]])->assertOk();

    expect((int) PollOption::whereKey($opt->id)->value('votes_count'))->toBe(2);
});

it('rejects a second choice on a single-choice poll', function (): void {
    $poll = votingPoll(['allow_multiple' => false]);
    $ids = $poll->options->pluck('id')->all();

    $this->withHeaders(['X-Client-Id' => 'c1'])
        ->postJson("/api/v1/polls/{$poll->uuid}/vote", ['option_ids' => $ids])
        ->assertStatus(422);
});

it('accepts multiple choices on a multi-choice poll', function (): void {
    $poll = votingPoll(['allow_multiple' => true]);
    $ids = $poll->options->pluck('id')->all();

    $this->withHeaders(['X-Client-Id' => 'c1'])
        ->postJson("/api/v1/polls/{$poll->uuid}/vote", ['option_ids' => $ids])
        ->assertOk();

    foreach ($ids as $id) {
        expect((int) PollOption::whereKey($id)->value('votes_count'))->toBe(1);
    }
    expect(PollVote::where('poll_id', $poll->id)->count())->toBe(1); // بطاقة واحدة، اختياران
});

it('rejects voting on an inactive poll', function (): void {
    $poll = votingPoll(['is_active' => false]);
    $opt = $poll->options->first();

    $this->withHeaders(['X-Client-Id' => 'c1'])
        ->postJson("/api/v1/polls/{$poll->uuid}/vote", ['option_ids' => [$opt->id]])
        ->assertStatus(422);
});

it('rejects voting before the scheduled start', function (): void {
    $poll = votingPoll(['starts_at' => now()->addDay()]);
    $opt = $poll->options->first();

    $this->withHeaders(['X-Client-Id' => 'c1'])
        ->postJson("/api/v1/polls/{$poll->uuid}/vote", ['option_ids' => [$opt->id]])
        ->assertStatus(422);
});

it('rejects voting after the poll has ended', function (): void {
    $poll = votingPoll(['ends_at' => now()->subDay()]);
    $opt = $poll->options->first();

    $this->withHeaders(['X-Client-Id' => 'c1'])
        ->postJson("/api/v1/polls/{$poll->uuid}/vote", ['option_ids' => [$opt->id]])
        ->assertStatus(422);
});

it('rejects an option that does not belong to the poll', function (): void {
    $poll = votingPoll();
    $foreign = PollOption::factory()->create(); // ينتمي لاستطلاع آخر

    $this->withHeaders(['X-Client-Id' => 'c1'])
        ->postJson("/api/v1/polls/{$poll->uuid}/vote", ['option_ids' => [$foreign->id]])
        ->assertStatus(422);
});

it('rejects guest votes on an authenticated-only poll', function (): void {
    $poll = votingPoll(['audience_mode' => 'authenticated']);
    $opt = $poll->options->first();

    $this->withHeaders(['X-Client-Id' => 'c1'])
        ->postJson("/api/v1/polls/{$poll->uuid}/vote", ['option_ids' => [$opt->id]])
        ->assertStatus(403);

    expect(PollVote::where('poll_id', $poll->id)->count())->toBe(0);
});

it('does not count a vote from a bot user-agent but still accepts', function (): void {
    $poll = votingPoll();
    $opt = $poll->options->first();

    $res = $this->withHeaders(['User-Agent' => 'Googlebot/2.1 (+http://www.google.com/bot.html)', 'X-Client-Id' => 'c1'])
        ->postJson("/api/v1/polls/{$poll->uuid}/vote", ['option_ids' => [$opt->id]]);

    $res->assertOk();
    expect((int) PollOption::whereKey($opt->id)->value('votes_count'))->toBe(0)
        ->and(PollVote::where('poll_id', $poll->id)->count())->toBe(0);
});

it('caps votes by a per-IP ceiling regardless of X-Client-Id rotation', function (): void {
    config(['polls.vote.per_ip_rate_limit' => 2]);
    $poll = votingPoll();
    $opt = $poll->options->first();

    $this->withHeaders(['X-Client-Id' => 'a'])->postJson("/api/v1/polls/{$poll->uuid}/vote", ['option_ids' => [$opt->id]])->assertOk();
    $this->withHeaders(['X-Client-Id' => 'b'])->postJson("/api/v1/polls/{$poll->uuid}/vote", ['option_ids' => [$opt->id]])->assertOk();
    // الطلب الثالث (عميل جديد، نفس IP) يتجاوز سقف الـ IP ⇒ 429.
    $this->withHeaders(['X-Client-Id' => 'c'])->postJson("/api/v1/polls/{$poll->uuid}/vote", ['option_ids' => [$opt->id]])->assertStatus(429);
});

it('returns 404 when voting on an unknown poll', function (): void {
    $this->withHeaders(['X-Client-Id' => 'c1'])
        ->postJson('/api/v1/polls/'.Str::uuid()->toString().'/vote', ['option_ids' => [1]])
        ->assertStatus(404);
});
