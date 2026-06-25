<?php

declare(strict_types=1);

use App\Models\Poll;
use App\Models\PollOption;
use App\Models\PollVote;
use App\Models\PollVoteOption;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Cache::flush(); // مفتاح الأسطول ثابت — تفريغ ضروري لمنع تسرّب الكاش بين الاختبارات.
    seedRoles();
});

function paSuperToken(): string
{
    $u = User::factory()->create();
    $u->assignRole('super_admin');

    return $u->createToken('admin', ['admin'])->plainTextToken;
}

/** بطاقة تصويت واحدة (مصوّت فريد) + اختياراته — مصدر الحقيقة الموثوق. */
function paVote(Poll $poll, array $optionIds, ?string $at = null): PollVote
{
    $vote = PollVote::factory()->create(['poll_id' => $poll->id, 'created_at' => $at ?? now()]);

    foreach ($optionIds as $optionId) {
        PollVoteOption::factory()->create(['poll_vote_id' => $vote->id, 'poll_option_id' => $optionId]);
    }

    return $vote;
}

// ─── Per-poll analytics ────────────────────────────────────────────────────────

it('returns exact unique voters vs total selections + authoritative distribution + zero-filled trend', function (): void {
    $token = paSuperToken();

    $poll = Poll::factory()->multiple()->create(); // allow_multiple → voters ≠ selections
    $a = PollOption::factory()->create(['poll_id' => $poll->id, 'label' => 'A', 'sort_order' => 0]);
    $b = PollOption::factory()->create(['poll_id' => $poll->id, 'label' => 'B', 'sort_order' => 1]);
    $c = PollOption::factory()->create(['poll_id' => $poll->id, 'label' => 'C', 'sort_order' => 2]);

    // 3 unique voters, 5 total selections: A=3, B=1, C=1.
    paVote($poll, [$a->id, $b->id]);
    paVote($poll, [$a->id]);
    paVote($poll, [$a->id, $c->id]);

    // votes_count counter intentionally left at 0 — analytics must NOT read it (authoritative truth only).
    expect((int) PollOption::whereKey($a->id)->value('votes_count'))->toBe(0);

    $res = $this->withToken($token)
        ->getJson("/api/v1/admin/polls/{$poll->id}/analytics?range=7d")
        ->assertOk();

    // Participation — unique voters is exact (presented normally, never "approximate").
    expect($res->json('data.participation.unique_voters'))->toBe(3);
    expect($res->json('data.participation.total_selections'))->toBe(5);
    expect($res->json('data.participation.avg_selections_per_voter'))->toEqual(1.67); // 5/3
    expect($res->json('data.participation.options_count'))->toBe(3);

    // Distribution from poll_vote_options (NOT votes_count), ordered by sort_order, % of selections.
    expect($res->json('data.distribution.0.label'))->toBe('A');
    expect($res->json('data.distribution.0.votes'))->toBe(3);
    expect($res->json('data.distribution.0.percentage'))->toEqual(60.0); // 3/5
    expect($res->json('data.distribution.1.votes'))->toBe(1);
    expect($res->json('data.distribution.1.percentage'))->toEqual(20.0);
    expect($res->json('data.distribution.2.votes'))->toBe(1);

    // Trend — zero-filled daily participation across the 7d window.
    expect($res->json('data.trend.window.range'))->toBe('7d');
    expect($res->json('data.trend.points'))->toHaveCount(7);
    expect($res->json('data.trend.totals.votes'))->toBe(3);

    // Entity meta.
    expect($res->json('data.entity.state'))->toBe('open');
    expect($res->json('data.entity.allow_multiple'))->toBeTrue();
});

it('returns zero-safe per-poll analytics for a poll with no votes', function (): void {
    $token = paSuperToken();

    $poll = Poll::factory()->create();
    PollOption::factory()->count(2)->create(['poll_id' => $poll->id]);

    $res = $this->withToken($token)
        ->getJson("/api/v1/admin/polls/{$poll->id}/analytics?range=30d")
        ->assertOk();

    expect($res->json('data.participation.unique_voters'))->toBe(0);
    expect($res->json('data.participation.total_selections'))->toBe(0);
    expect($res->json('data.participation.avg_selections_per_voter'))->toEqual(0.0);
    expect($res->json('data.distribution.0.percentage'))->toEqual(0.0);
    expect($res->json('data.trend.points'))->toHaveCount(30);
    expect($res->json('data.trend.totals.votes'))->toBe(0);
});

// ─── Fleet analytics ───────────────────────────────────────────────────────────

it('returns fleet poll analytics (kpis, status breakdown, leaderboard, recent participation)', function (): void {
    $token = paSuperToken();

    $open1 = Poll::factory()->create();                                            // open
    PollOption::factory()->count(2)->create(['poll_id' => $open1->id]);
    $opt1 = $open1->options()->first();
    paVote($open1, [$opt1->id]);
    paVote($open1, [$opt1->id]);
    paVote($open1, [$opt1->id]); // 3 unique voters → leaderboard #1

    $open2 = Poll::factory()->create();                                            // open
    PollOption::factory()->count(2)->create(['poll_id' => $open2->id]);
    paVote($open2, [$open2->options()->first()->id]); // 1 unique voter

    Poll::factory()->inactive()->create();                                         // inactive
    Poll::factory()->create(['starts_at' => now()->addDay()]);                     // scheduled
    Poll::factory()->create(['starts_at' => now()->subDays(2), 'ends_at' => now()->subDay()]); // closed

    $res = $this->withToken($token)
        ->getJson('/api/v1/admin/polls/analytics')
        ->assertOk();

    // KPIs.
    expect($res->json('data.kpis.total_polls'))->toBe(5);
    expect($res->json('data.kpis.active_polls'))->toBe(4);  // all except the inactive one
    expect($res->json('data.kpis.open_polls'))->toBe(2);    // open1 + open2
    expect($res->json('data.kpis.total_votes'))->toBe(4);   // 3 + 1
    expect($res->json('data.kpis.total_selections'))->toBe(4);

    // Status breakdown (derived live).
    expect($res->json('data.status_breakdown.open'))->toBe(2);
    expect($res->json('data.status_breakdown.scheduled'))->toBe(1);
    expect($res->json('data.status_breakdown.closed'))->toBe(1);
    expect($res->json('data.status_breakdown.inactive'))->toBe(1);

    // Leaderboard ordered by unique voters (most participated first).
    expect($res->json('data.top_polls.0.id'))->toBe($open1->id);
    expect($res->json('data.top_polls.0.unique_voters'))->toBe(3);
    expect($res->json('data.top_polls.1.unique_voters'))->toBe(1);

    // Recent participation — 30 zero-filled daily points, totals = all votes today.
    expect($res->json('data.recent_participation.points'))->toHaveCount(30);
    expect($res->json('data.recent_participation.totals.votes'))->toBe(4);
});

// ─── Permissions ─────────────────────────────────────────────────────────────

it('requires polls.view for both poll analytics surfaces', function (): void {
    $poll = Poll::factory()->create();
    $token = User::factory()->create()->createToken('admin', ['admin'])->plainTextToken; // no roles

    $this->withToken($token)->getJson('/api/v1/admin/polls/analytics')->assertStatus(403);
    $this->withToken($token)->getJson("/api/v1/admin/polls/{$poll->id}/analytics")->assertStatus(403);
});
