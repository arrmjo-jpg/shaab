<?php

declare(strict_types=1);

use App\Models\Poll;
use App\Models\PollOption;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::flush());

function readPoll(array $overrides = [], int $options = 2): Poll
{
    $poll = Poll::factory()->create(array_merge(['is_active' => true], $overrides));
    PollOption::factory()->count($options)->create(['poll_id' => $poll->id]);

    return $poll->load('options');
}

it('hydrates the poll definition with per-actor state', function (): void {
    $poll = readPoll(['result_visibility' => 'after_vote']);

    $res = $this->withHeaders(['X-Client-Id' => 'c1'])->getJson("/api/v1/polls/{$poll->uuid}");

    $res->assertOk();
    expect($res->json('data.uuid'))->toBe($poll->uuid)
        ->and($res->json('data.is_open'))->toBeTrue()
        ->and($res->json('data.has_voted'))->toBeFalse()
        ->and($res->json('data.results'))->toBeNull()
        ->and($res->json('data.options'))->toHaveCount(2);
});

it('always shows results when result_visibility is always', function (): void {
    $poll = readPoll(['result_visibility' => 'always']);

    expect($this->getJson("/api/v1/polls/{$poll->uuid}")->json('data.results'))->not->toBeNull();
});

it('shows after_vote results only to the voter (strict)', function (): void {
    $poll = readPoll(['result_visibility' => 'after_vote']);
    $opt = $poll->options->first();

    $this->withHeaders(['X-Client-Id' => 'c1'])->postJson("/api/v1/polls/{$poll->uuid}/vote", ['option_ids' => [$opt->id]])->assertOk();

    $voter = $this->withHeaders(['X-Client-Id' => 'c1'])->getJson("/api/v1/polls/{$poll->uuid}");
    expect($voter->json('data.has_voted'))->toBeTrue()
        ->and($voter->json('data.results'))->not->toBeNull();

    $nonVoter = $this->withHeaders(['X-Client-Id' => 'c2'])->getJson("/api/v1/polls/{$poll->uuid}");
    expect($nonVoter->json('data.has_voted'))->toBeFalse()
        ->and($nonVoter->json('data.results'))->toBeNull();
});

it('never reveals after_vote results to a non-voter even after close (strict)', function (): void {
    $poll = readPoll(['result_visibility' => 'after_vote', 'is_active' => false]);

    expect($this->withHeaders(['X-Client-Id' => 'never'])->getJson("/api/v1/polls/{$poll->uuid}")->json('data.results'))
        ->toBeNull();
});

it('reveals after_close results on the results endpoint only once closed', function (): void {
    $open = readPoll(['result_visibility' => 'after_close', 'is_active' => true]);
    expect($this->getJson("/api/v1/polls/{$open->uuid}/results")->json('data.visible'))->toBeFalse();

    $closed = readPoll(['result_visibility' => 'after_close', 'is_active' => false]);
    expect($this->getJson("/api/v1/polls/{$closed->uuid}/results")->json('data.visible'))->toBeTrue();
});

it('never reveals after_vote results on the global results endpoint (per-actor only)', function (): void {
    $poll = readPoll(['result_visibility' => 'after_vote']);

    expect($this->getJson("/api/v1/polls/{$poll->uuid}/results")->json('data.visible'))->toBeFalse();
});

it('returns 404 for an unknown poll uuid', function (): void {
    $this->getJson('/api/v1/polls/'.Str::uuid()->toString())->assertStatus(404);
});

it('returns 404 for a soft-deleted poll', function (): void {
    $poll = readPoll();
    $poll->delete();

    $this->getJson("/api/v1/polls/{$poll->uuid}")->assertStatus(404);
});
