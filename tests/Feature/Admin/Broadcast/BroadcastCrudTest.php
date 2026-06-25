<?php

declare(strict_types=1);

use App\Actions\Admin\Broadcast\CreateBroadcastAction;
use App\Models\Broadcast;
use App\Models\BroadcastCategory;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
});

function bcSuper(): string
{
    $u = User::factory()->create();
    $u->assignRole('super_admin');

    return $u->createToken('admin', ['admin'])->plainTextToken;
}

/** محرّر بصلاحيات مُمرّرة فقط (لا شيء افتراضياً للبثّ) — لاختبار الحارس. */
function bcActor(string ...$perms): string
{
    $role = Role::findByName('editor', 'web');
    if ($perms !== []) {
        $role->givePermissionTo($perms);
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $u = User::factory()->create();
    $u->assignRole('editor');

    return $u->createToken('admin', ['admin'])->plainTextToken;
}

/** @return array<string,mixed> */
function bcValidPayload(array $overrides = []): array
{
    return array_merge([
        'title' => 'بثّ مباشر للمباراة',
        'kind' => 'live',
        'source_type' => 'youtube_live',
        'source_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    ], $overrides);
}

// ─── Broadcasts: create ──────────────────────────────────────────────────────

it('creates a broadcast that starts as draft with auto slug/uuid + creator', function (): void {
    $token = bcSuper();

    $res = $this->withToken($token)->postJson('/api/v1/admin/broadcasts', bcValidPayload())
        ->assertCreated();

    expect($res->json('data.status'))->toBe('draft');
    expect($res->json('data.uuid'))->not->toBeEmpty();
    expect($res->json('data.slug'))->not->toBeEmpty();
    expect($res->json('data.is_public'))->toBeFalse();
    expect(Broadcast::firstWhere('uuid', $res->json('data.uuid'))->created_by)->not->toBeNull();
});

it('forces draft on create even if a status is supplied (transitions deferred to B2)', function (): void {
    $token = bcSuper();

    $this->withToken($token)->postJson('/api/v1/admin/broadcasts', bcValidPayload(['status' => 'live']))
        ->assertCreated()
        ->assertJsonPath('data.status', 'draft');
});

it('rejects creation with missing required fields', function (): void {
    $token = bcSuper();

    $this->withToken($token)->postJson('/api/v1/admin/broadcasts', [])
        ->assertStatus(422);
});

it('rejects creation with an untrusted source url', function (): void {
    $token = bcSuper();

    $this->withToken($token)->postJson('/api/v1/admin/broadcasts', bcValidPayload([
        'source_url' => 'https://evil.example/live',
    ]))->assertStatus(422);

    expect(Broadcast::count())->toBe(0);
});

it('requires broadcasts.create to create', function (): void {
    $token = bcActor('broadcasts.view');

    $this->withToken($token)->postJson('/api/v1/admin/broadcasts', bcValidPayload())
        ->assertStatus(403);
});

// ─── Broadcasts: read / list ─────────────────────────────────────────────────

it('lists broadcasts filtered by kind', function (): void {
    $token = bcSuper();
    Broadcast::factory()->create();              // live (default)
    Broadcast::factory()->tv()->create();        // tv

    $res = $this->withToken($token)->getJson('/api/v1/admin/broadcasts?filter[kind]=tv')->assertOk();

    expect($res->json('data'))->toHaveCount(1);
    expect($res->json('data.0.kind'))->toBe('tv');
});

it('shows a broadcast', function (): void {
    $token = bcSuper();
    $b = Broadcast::factory()->create();

    $this->withToken($token)->getJson("/api/v1/admin/broadcasts/{$b->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $b->id);
});

it('requires broadcasts.view to list', function (): void {
    $token = bcActor();

    $this->withToken($token)->getJson('/api/v1/admin/broadcasts')->assertStatus(403);
});

// ─── Broadcasts: update ──────────────────────────────────────────────────────

it('updates broadcast metadata and stamps updated_by', function (): void {
    $token = bcSuper();
    $b = Broadcast::factory()->create();

    $this->withToken($token)->putJson("/api/v1/admin/broadcasts/{$b->id}", [
        'title' => 'عنوان محدّث',
        'is_featured' => true,
    ])->assertOk()->assertJsonPath('data.title', 'عنوان محدّث');

    $fresh = $b->fresh();
    expect($fresh->is_featured)->toBeTrue();
    expect($fresh->updated_by)->not->toBeNull();
});

it('rejects update with an untrusted source', function (): void {
    $token = bcSuper();
    $b = Broadcast::factory()->create();

    $this->withToken($token)->putJson("/api/v1/admin/broadcasts/{$b->id}", [
        'source_type' => 'youtube_live',
        'source_url' => 'https://evil.example/live',
    ])->assertStatus(422);
});

it('requires broadcasts.edit to update', function (): void {
    $token = bcActor('broadcasts.view');
    $b = Broadcast::factory()->create();

    $this->withToken($token)->putJson("/api/v1/admin/broadcasts/{$b->id}", ['title' => 'x'])
        ->assertStatus(403);
});

// ─── Broadcasts: delete ──────────────────────────────────────────────────────

it('soft-deletes a broadcast', function (): void {
    $token = bcSuper();
    $b = Broadcast::factory()->create();

    $this->withToken($token)->deleteJson("/api/v1/admin/broadcasts/{$b->id}")->assertOk();

    expect(Broadcast::find($b->id))->toBeNull();
    expect(Broadcast::withTrashed()->find($b->id)->trashed())->toBeTrue();
});

it('requires broadcasts.delete to delete', function (): void {
    $token = bcActor('broadcasts.view');
    $b = Broadcast::factory()->create();

    $this->withToken($token)->deleteJson("/api/v1/admin/broadcasts/{$b->id}")->assertStatus(403);
});

// ─── Source-safety invariant enforced at the Action boundary ─────────────────

it('enforces source safety at the action boundary (bypassing the form request)', function (): void {
    $actor = User::factory()->create();

    $res = (new CreateBroadcastAction)->handle([
        'title' => 'x',
        'kind' => 'live',
        'source_type' => 'youtube_live',
        'source_url' => 'https://evil.example/live',
    ], $actor);

    expect($res->getStatusCode())->toBe(422);
    expect(Broadcast::count())->toBe(0);
});

// ─── Broadcast categories (flat) CRUD + RBAC ─────────────────────────────────

it('creates a flat broadcast category', function (): void {
    $token = bcSuper();

    $res = $this->withToken($token)->postJson('/api/v1/admin/broadcast-categories', [
        'name' => 'رياضة',
    ])->assertCreated();

    expect($res->json('data.slug'))->not->toBeEmpty();
    expect($res->json('data.is_active'))->toBeTrue();
});

it('requires broadcast-categories.manage to create a category', function (): void {
    $token = bcActor('broadcast-categories.view');

    $this->withToken($token)->postJson('/api/v1/admin/broadcast-categories', ['name' => 'رياضة'])
        ->assertStatus(403);
});

it('validates the category name on create', function (): void {
    $token = bcSuper();

    $this->withToken($token)->postJson('/api/v1/admin/broadcast-categories', [])
        ->assertStatus(422);
});

it('lists flat categories with a broadcasts count', function (): void {
    $token = bcSuper();
    $cat = BroadcastCategory::factory()->create();
    Broadcast::factory()->for($cat, 'category')->create();

    $res = $this->withToken($token)->getJson('/api/v1/admin/broadcast-categories')->assertOk();

    $row = collect($res->json('data'))->firstWhere('id', $cat->id);
    expect($row['broadcasts_count'])->toBe(1);
});

it('updates and soft-deletes a category', function (): void {
    $token = bcSuper();
    $cat = BroadcastCategory::factory()->create();

    $this->withToken($token)->putJson("/api/v1/admin/broadcast-categories/{$cat->id}", [
        'name' => 'منوعات',
        'is_active' => false,
    ])->assertOk()->assertJsonPath('data.name', 'منوعات');

    expect($cat->fresh()->is_active)->toBeFalse();

    $this->withToken($token)->deleteJson("/api/v1/admin/broadcast-categories/{$cat->id}")->assertOk();
    expect(BroadcastCategory::find($cat->id))->toBeNull();
    expect(BroadcastCategory::withTrashed()->find($cat->id)->trashed())->toBeTrue();
});
