<?php

declare(strict_types=1);

use App\Enums\BroadcastStatus;
use App\Support\Broadcast\BroadcastTransitionGuard;

// ─── State machine: allowed vs forbidden transitions (pure) ──────────────────

it('permits exactly the defined transitions and rejects the rest', function (string $from, string $to, bool $allowed): void {
    expect(BroadcastStatus::from($from)->canTransitionTo(BroadcastStatus::from($to)))->toBe($allowed);
})->with([
    // ── allowed ──
    'draft→scheduled' => ['draft', 'scheduled', true],
    'draft→archived' => ['draft', 'archived', true],
    'scheduled→live' => ['scheduled', 'live', true],
    'scheduled→failed' => ['scheduled', 'failed', true],
    'scheduled→archived' => ['scheduled', 'archived', true],
    'live→offline' => ['live', 'offline', true],
    'live→ended' => ['live', 'ended', true],
    'live→failed' => ['live', 'failed', true],
    'offline→live' => ['offline', 'live', true],
    'offline→ended' => ['offline', 'ended', true],
    'offline→failed' => ['offline', 'failed', true],
    'failed→archived' => ['failed', 'archived', true],
    'ended→archived' => ['ended', 'archived', true],
    // ── forbidden ──
    'ended→live' => ['ended', 'live', false],
    'archived→scheduled' => ['archived', 'scheduled', false],
    'archived→live' => ['archived', 'live', false],
    'failed→live' => ['failed', 'live', false],
    'draft→live' => ['draft', 'live', false],
    'live→scheduled' => ['live', 'scheduled', false],
    'archived→archived' => ['archived', 'archived', false],
    'ended→offline' => ['ended', 'offline', false],
]);

it('archived is a terminal state with no outgoing transitions', function (): void {
    expect(BroadcastStatus::Archived->allowedTransitions())->toBe([]);
});

it('guard returns null for a legal transition and a 422 denial for an illegal one', function (): void {
    expect(BroadcastTransitionGuard::check(BroadcastStatus::Draft, BroadcastStatus::Scheduled))->toBeNull();

    $denied = BroadcastTransitionGuard::check(BroadcastStatus::Ended, BroadcastStatus::Live);
    expect($denied)->not->toBeNull();
    expect($denied->getStatusCode())->toBe(422);
});
