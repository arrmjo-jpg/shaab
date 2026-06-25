<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\WriterRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
});

function publicUserToken(): array
{
    $u = User::factory()->create();
    $u->assignRole('user');

    return [$u, $u->createToken('public', ['user'])->plainTextToken];
}

function adminToken(): array
{
    $a = User::factory()->create();
    $a->assignRole('super_admin');

    return [$a, $a->createToken('admin', ['admin'])->plainTextToken];
}

// ─── Public submission ──────────────────────────────────────────────────

it('lets an authenticated user submit a writer request', function (): void {
    [$user, $token] = publicUserToken();

    $this->withToken($token)
        ->postJson('/api/v1/writer-requests', ['note' => 'أريد الكتابة'])
        ->assertCreated();

    expect(WriterRequest::where('user_id', $user->id)->where('status', 'pending')->exists())
        ->toBeTrue();
});

it('blocks a duplicate pending request', function (): void {
    [$user, $token] = publicUserToken();
    WriterRequest::create(['user_id' => $user->id, 'status' => 'pending']);

    $this->withToken($token)
        ->postJson('/api/v1/writer-requests', [])
        ->assertStatus(422);
});

it('allows a rejected user to reapply (intentional policy)', function (): void {
    [$user, $token] = publicUserToken();
    WriterRequest::create(['user_id' => $user->id, 'status' => 'rejected']);

    $this->withToken($token)
        ->postJson('/api/v1/writer-requests', ['note' => 'محاولة ثانية'])
        ->assertCreated();

    expect($user->writerRequests()->where('status', 'pending')->count())->toBe(1);
});

it('blocks a request when the user is already a writer', function (): void {
    [$user, $token] = publicUserToken();
    $user->update(['is_writer' => true]);

    $this->withToken($token)
        ->postJson('/api/v1/writer-requests', [])
        ->assertStatus(422);
});

it('denies submission without a user-ability token', function (): void {
    $this->postJson('/api/v1/writer-requests', [])->assertStatus(401);
});

// ─── Admin review ───────────────────────────────────────────────────────

it('lists writer requests for an authorized admin', function (): void {
    [, $token] = adminToken();
    $u = User::factory()->create();
    WriterRequest::create(['user_id' => $u->id, 'status' => 'pending']);

    $response = $this->withToken($token)->getJson('/api/v1/admin/writer-requests');

    $response->assertOk();
    assertSuccessContract($response);
    $response->assertJsonStructure([
        'data' => [['id', 'status', 'status_label', 'user' => ['id', 'name', 'email']]],
        'meta' => ['pagination' => ['total']],
    ]);
});

it('approves a request and promotes the user to writer', function (): void {
    [, $token] = adminToken();
    $u = User::factory()->create(['is_writer' => false]);
    $req = WriterRequest::create(['user_id' => $u->id, 'status' => 'pending']);

    $this->withToken($token)
        ->postJson("/api/v1/admin/writer-requests/{$req->id}/approve")
        ->assertOk();

    expect($req->fresh()->status->value)->toBe('approved');
    expect($u->fresh()->is_writer)->toBeTrue();
});

it('rejects a request without promoting', function (): void {
    [, $token] = adminToken();
    $u = User::factory()->create(['is_writer' => false]);
    $req = WriterRequest::create(['user_id' => $u->id, 'status' => 'pending']);

    $this->withToken($token)
        ->postJson("/api/v1/admin/writer-requests/{$req->id}/reject")
        ->assertOk();

    expect($req->fresh()->status->value)->toBe('rejected');
    expect($u->fresh()->is_writer)->toBeFalse();
});

it('cannot review a non-pending request', function (): void {
    [, $token] = adminToken();
    $u = User::factory()->create();
    $req = WriterRequest::create(['user_id' => $u->id, 'status' => 'approved']);

    $this->withToken($token)
        ->postJson("/api/v1/admin/writer-requests/{$req->id}/approve")
        ->assertStatus(422);
});

it('denies admin writer-requests to an admin lacking the permission', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('editor'); // بلا writer-requests.view
    $token = $admin->createToken('admin', ['admin'])->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/admin/writer-requests')->assertStatus(403);
});

// ─── is_writer toggle via user management ───────────────────────────────

it('toggles is_writer via admin user update and reflects in the contract', function (): void {
    [, $token] = adminToken();
    $target = User::factory()->create(['is_writer' => false]);

    $this->withToken($token)
        ->putJson("/api/v1/admin/users/{$target->id}", ['is_writer' => true])
        ->assertOk();

    expect($target->fresh()->is_writer)->toBeTrue();

    $show = $this->withToken($token)->getJson("/api/v1/admin/users/{$target->id}");
    expect($show->json('data.is_writer'))->toBeTrue();
});

// ─── Hardening: rate limiting + atomicity (P1.4) ──────────────────────────

it('throttles writer-request submissions at 5/minute per user', function (): void {
    [, $token] = publicUserToken();

    // 5 محاولات تمرّ عبر الـthrottle (1 منشأة + 4 مرفوضة لوجود pending)، السادسة تُخنَق.
    for ($i = 0; $i < 5; $i++) {
        $this->withToken($token)->postJson('/api/v1/writer-requests', []);
    }

    $this->withToken($token)->postJson('/api/v1/writer-requests', [])->assertStatus(429);
});

it('reject of a non-pending request returns 422 (atomic re-check path)', function (): void {
    [, $token] = adminToken();
    $u = User::factory()->create();
    $req = WriterRequest::create(['user_id' => $u->id, 'status' => 'approved']);

    $this->withToken($token)
        ->postJson("/api/v1/admin/writer-requests/{$req->id}/reject")
        ->assertStatus(422);

    expect($req->fresh()->status->value)->toBe('approved');
});
