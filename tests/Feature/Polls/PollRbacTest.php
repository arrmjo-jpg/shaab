<?php

declare(strict_types=1);

use App\Models\Poll;
use App\Models\PollOption;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(fn () => seedRoles());

/** توكن لمستخدم بصلاحيات مُمرّرة مباشرةً (واحد لكل اختبار — لتفادي تلوّث كاش الصلاحيات). */
function pollActor(string ...$perms): string
{
    $u = User::factory()->create();
    $u->assignRole('editor');
    if ($perms !== []) {
        $u->givePermissionTo($perms);
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $u->createToken('admin', ['admin'])->plainTextToken;
}

it('denies poll endpoints without permission', function (): void {
    $token = pollActor();

    $this->withToken($token)->getJson('/api/v1/admin/polls')->assertStatus(403);
    $this->withToken($token)->postJson('/api/v1/admin/polls', [
        'question' => 'x', 'options' => [['label' => 'A'], ['label' => 'B']],
    ])->assertStatus(403);
});

it('allows listing with polls.view', function (): void {
    $this->withToken(pollActor('polls.view'))
        ->getJson('/api/v1/admin/polls')
        ->assertOk();
});

it('allows creating with polls.create', function (): void {
    $this->withToken(pollActor('polls.create'))->postJson('/api/v1/admin/polls', [
        'question' => 'Q?',
        'options' => [['label' => 'A'], ['label' => 'B']],
    ])->assertStatus(201);
});

it('forbids the active toggle with only polls.edit (publish-gated)', function (): void {
    $poll = Poll::factory()->inactive()->create();

    $this->withToken(pollActor('polls.edit'))
        ->patchJson("/api/v1/admin/polls/{$poll->id}/active")
        ->assertStatus(403);

    expect($poll->fresh()->is_active)->toBeFalse();
});

it('allows the active toggle with polls.publish', function (): void {
    $poll = Poll::factory()->inactive()->create();

    $this->withToken(pollActor('polls.publish'))
        ->patchJson("/api/v1/admin/polls/{$poll->id}/active")
        ->assertOk();

    expect($poll->fresh()->is_active)->toBeTrue();
});

it('ignores is_active on create — poll stays inactive (publish-only path)', function (): void {
    $res = $this->withToken(pollActor('polls.create'))->postJson('/api/v1/admin/polls', [
        'question' => 'Q?',
        'is_active' => true,
        'options' => [['label' => 'A'], ['label' => 'B']],
    ])->assertStatus(201);

    expect($res->json('data.is_active'))->toBeFalse();
});

it('ignores is_active on update — poll stays inactive (publish-only path)', function (): void {
    $poll = Poll::factory()->inactive()->create();
    PollOption::factory()->count(2)->create(['poll_id' => $poll->id]);

    $this->withToken(pollActor('polls.edit'))->putJson("/api/v1/admin/polls/{$poll->id}", [
        'question' => 'Q2',
        'is_active' => true,
        'options' => [['label' => 'A'], ['label' => 'B']],
    ])->assertOk();

    expect($poll->fresh()->is_active)->toBeFalse();
});

it('forbids force-delete with only polls.delete', function (): void {
    $poll = Poll::factory()->create();
    $poll->delete();

    $this->withToken(pollActor('polls.delete'))
        ->deleteJson("/api/v1/admin/polls/{$poll->id}/force")
        ->assertStatus(403);
});

it('allows force-delete with polls.force_delete', function (): void {
    $poll = Poll::factory()->create();
    $poll->delete();

    $this->withToken(pollActor('polls.force_delete'))
        ->deleteJson("/api/v1/admin/polls/{$poll->id}/force")
        ->assertOk();
});
