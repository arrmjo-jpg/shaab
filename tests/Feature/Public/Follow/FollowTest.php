<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
});

/** @return array{0: User, 1: string} */
function followActor(): array
{
    $user = User::factory()->create();
    $user->assignRole('user');
    $token = $user->createToken('public-token', ['user'])->plainTextToken;

    return [$user, $token];
}

it('follows a team and records it in the activity log', function (): void {
    [$user, $token] = followActor();

    $res = $this->withToken($token)->postJson('/api/v1/follow/team/5087')->assertOk();
    expect($res->json('data.following'))->toBeTrue();

    $this->assertDatabaseHas('follows', [
        'user_id' => $user->id,
        'followable_type' => 'team',
        'followable_id' => 5087,
    ]);
    $this->assertDatabaseHas('activity_log', ['log_name' => 'follow', 'event' => 'created']);
});

it('toggles off an existing follow (unfollow) and logs the deletion', function (): void {
    [$user, $token] = followActor();
    $this->withToken($token)->postJson('/api/v1/follow/team/5087')->assertOk();

    $res = $this->withToken($token)->postJson('/api/v1/follow/team/5087')->assertOk();
    expect($res->json('data.following'))->toBeFalse();

    $this->assertDatabaseMissing('follows', [
        'user_id' => $user->id,
        'followable_type' => 'team',
        'followable_id' => 5087,
    ]);
    $this->assertDatabaseHas('activity_log', ['log_name' => 'follow', 'event' => 'deleted']);
});

it('reports the follow state for the current user', function (): void {
    [, $token] = followActor();

    $this->withToken($token)->getJson('/api/v1/follow/team/5087')
        ->assertOk()->assertJsonPath('data.following', false);

    $this->withToken($token)->postJson('/api/v1/follow/team/5087')->assertOk();

    $this->withToken($token)->getJson('/api/v1/follow/team/5087')
        ->assertOk()->assertJsonPath('data.following', true);
});

it('lists the user follows and filters by type', function (): void {
    [, $token] = followActor();
    $this->withToken($token)->postJson('/api/v1/follow/team/5087')->assertOk();
    $this->withToken($token)->postJson('/api/v1/follow/competition/5930')->assertOk();

    $all = $this->withToken($token)->getJson('/api/v1/follows')->assertOk();
    expect($all->json('data.follows'))->toHaveCount(2);

    $teams = $this->withToken($token)->getJson('/api/v1/follows?type=team')->assertOk();
    expect($teams->json('data.follows'))->toHaveCount(1)
        ->and($teams->json('data.follows.0.type'))->toBe('team')
        ->and($teams->json('data.follows.0.id'))->toBe(5087);
});

it('rejects an unsupported followable type', function (): void {
    [, $token] = followActor();
    $this->withToken($token)->postJson('/api/v1/follow/banana/5087')->assertStatus(422);
});

it('denies an unauthenticated guest', function (): void {
    $this->postJson('/api/v1/follow/team/5087')->assertStatus(401);
});
