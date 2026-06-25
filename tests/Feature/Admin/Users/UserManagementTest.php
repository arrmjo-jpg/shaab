<?php

declare(strict_types=1);

use App\Enums\UserStatus;
use App\Models\User;
use App\Notifications\QueuedResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Testing\File;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
});

/** مدير super_admin + token إداري */
function usersAdminToken(): array
{
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    return [$admin, $admin->createToken('admin-token', ['admin'])->plainTextToken];
}

// ─── Listing + QueryBuilder paths (تغطية إصلاح variadic) ───────────────

it('lists users with the unified contract and pagination meta', function (): void {
    [, $token] = usersAdminToken();
    User::factory()->count(3)->create();

    $response = $this->withToken($token)->getJson('/api/v1/admin/users');

    $response->assertOk();
    assertSuccessContract($response);
    $response->assertJsonStructure([
        'data', 'meta' => ['pagination' => ['total', 'per_page', 'current_page', 'total_pages']],
    ]);
});

it('exercises the allowedFilters variadic path (status filter)', function (): void {
    [, $token] = usersAdminToken();
    User::factory()->banned()->create(['email' => 'banned@example.com']);

    $response = $this->withToken($token)
        ->getJson('/api/v1/admin/users?filter[status]=banned');

    $response->assertOk();
    assertSuccessContract($response);
});

it('exercises the allowedSorts variadic path', function (): void {
    [, $token] = usersAdminToken();
    User::factory()->count(2)->create();

    $this->withToken($token)
        ->getJson('/api/v1/admin/users?sort=-created_at')
        ->assertOk();

    $this->withToken($token)
        ->getJson('/api/v1/admin/users?sort=last_login_at')
        ->assertOk();
});

it('exercises the search and role filters', function (): void {
    [, $token] = usersAdminToken();
    User::factory()->create(['name' => 'بحث فريد', 'email' => 'unique@example.com']);

    $this->withToken($token)
        ->getJson('/api/v1/admin/users?filter[search]=unique')
        ->assertOk();

    $this->withToken($token)
        ->getJson('/api/v1/admin/users?filter[role]=super_admin')
        ->assertOk();
});

it('uses pagination defaults and clamps max from config/performance', function (): void {
    [, $token] = usersAdminToken();

    $default = $this->withToken($token)->getJson('/api/v1/admin/users')
        ->json('meta.pagination.per_page');
    $clamped = $this->withToken($token)->getJson('/api/v1/admin/users?per_page=99999')
        ->json('meta.pagination.per_page');

    expect($default)->toBe((int) config('performance.pagination.default'));
    expect($clamped)->toBe((int) config('performance.pagination.max'));
});

// ─── CRUD smoke ────────────────────────────────────────────────────────

it('creates a user with roles', function (): void {
    [, $token] = usersAdminToken();

    $response = $this->withToken($token)->postJson('/api/v1/admin/users', [
        'name' => 'مستخدم جديد',
        'email' => 'new@example.com',
        'password' => 'StrongPass123!',
        'password_confirmation' => 'StrongPass123!',
        'roles' => ['editor'],
    ]);

    $response->assertCreated();
    assertSuccessContract($response);
    expect(User::where('email', 'new@example.com')->first()->hasRole('editor'))->toBeTrue();
});

it('shows and updates a user', function (): void {
    [, $token] = usersAdminToken();
    $target = User::factory()->create(['name' => 'قديم']);

    $this->withToken($token)->getJson("/api/v1/admin/users/{$target->id}")->assertOk();

    $this->withToken($token)->putJson("/api/v1/admin/users/{$target->id}", [
        'name' => 'محدّث',
    ])->assertOk();

    expect($target->fresh()->name)->toBe('محدّث');
});

it('updates a user status', function (): void {
    [, $token] = usersAdminToken();
    $target = User::factory()->create();

    $this->withToken($token)->patchJson("/api/v1/admin/users/{$target->id}/status", [
        'status' => 'suspended',
    ])->assertOk();

    expect($target->fresh()->status)->toBe(UserStatus::Suspended);
});

// ─── Business rules ────────────────────────────────────────────────────

it('prevents changing your own status (self-lockout)', function (): void {
    [$admin, $token] = usersAdminToken();

    $this->withToken($token)->patchJson("/api/v1/admin/users/{$admin->id}/status", [
        'status' => 'suspended',
    ])->assertStatus(403);
});

it('prevents removing all admin roles from yourself', function (): void {
    [$admin, $token] = usersAdminToken();

    $this->withToken($token)->putJson("/api/v1/admin/users/{$admin->id}", [
        'roles' => [],
    ])->assertStatus(403);

    expect($admin->fresh()->hasRole('super_admin'))->toBeTrue();
});

it('prevents downgrading yourself to a non-admin role', function (): void {
    [$admin, $token] = usersAdminToken();

    $this->withToken($token)->putJson("/api/v1/admin/users/{$admin->id}", [
        'roles' => ['user'],
    ])->assertStatus(403);

    expect($admin->fresh()->hasRole('super_admin'))->toBeTrue();
});

it('allows changing your own admin role to another admin role', function (): void {
    [$admin, $token] = usersAdminToken();

    $this->withToken($token)->putJson("/api/v1/admin/users/{$admin->id}", [
        'roles' => ['super_admin', 'editor'],
    ])->assertOk();

    expect($admin->fresh()->hasRole('editor'))->toBeTrue();
});

it('prevents deleting yourself', function (): void {
    [$admin, $token] = usersAdminToken();

    $this->withToken($token)->deleteJson("/api/v1/admin/users/{$admin->id}")
        ->assertStatus(403);
});

it('prevents deleting a super_admin user', function (): void {
    [, $token] = usersAdminToken();
    $other = User::factory()->create();
    $other->assignRole('super_admin');

    $this->withToken($token)->deleteJson("/api/v1/admin/users/{$other->id}")
        ->assertStatus(403);
});

it('deletes an ordinary user', function (): void {
    [, $token] = usersAdminToken();
    $target = User::factory()->create();

    $this->withToken($token)->deleteJson("/api/v1/admin/users/{$target->id}")
        ->assertOk();

    expect(User::find($target->id))->toBeNull();
});

it('creates a user verified or unverified per email_verified flag', function (): void {
    [, $token] = usersAdminToken();

    $this->withToken($token)->postJson('/api/v1/admin/users', [
        'name' => 'مؤكَّد',
        'email' => 'verified@example.com',
        'password' => 'StrongPass123!',
        'password_confirmation' => 'StrongPass123!',
        'email_verified' => true,
    ])->assertCreated();

    $this->withToken($token)->postJson('/api/v1/admin/users', [
        'name' => 'غير مؤكَّد',
        'email' => 'unverified@example.com',
        'password' => 'StrongPass123!',
        'password_confirmation' => 'StrongPass123!',
    ])->assertCreated();

    expect(User::where('email', 'verified@example.com')->first()->email_verified_at)->not->toBeNull();
    expect(User::where('email', 'unverified@example.com')->first()->email_verified_at)->toBeNull();
});

it('toggles email verification on update', function (): void {
    [, $token] = usersAdminToken();
    $target = User::factory()->create(); // مؤكَّد (factory)

    $this->withToken($token)->putJson("/api/v1/admin/users/{$target->id}", [
        'email_verified' => false,
    ])->assertOk();
    expect($target->fresh()->email_verified_at)->toBeNull();

    $this->withToken($token)->putJson("/api/v1/admin/users/{$target->id}", [
        'email_verified' => true,
    ])->assertOk();
    expect($target->fresh()->email_verified_at)->not->toBeNull();
});

it('exposes email_verified in the user contract', function (): void {
    [, $token] = usersAdminToken();
    $target = User::factory()->unverified()->create();

    $response = $this->withToken($token)->getJson("/api/v1/admin/users/{$target->id}");
    $response->assertOk();
    expect($response->json('data.email_verified'))->toBeFalse();
});

it('soft deletes then restores a user', function (): void {
    [, $token] = usersAdminToken();
    $target = User::factory()->create();

    $this->withToken($token)->deleteJson("/api/v1/admin/users/{$target->id}")->assertOk();
    expect(User::find($target->id))->toBeNull();
    expect(User::withTrashed()->find($target->id)->trashed())->toBeTrue();

    $this->withToken($token)->postJson("/api/v1/admin/users/{$target->id}/restore")
        ->assertOk();

    expect(User::find($target->id))->not->toBeNull();
});

it('rejects restoring a user that is not deleted', function (): void {
    [, $token] = usersAdminToken();
    $target = User::factory()->create();

    $this->withToken($token)->postJson("/api/v1/admin/users/{$target->id}/restore")
        ->assertStatus(422);
});

it('lists only trashed users via the trashed filter', function (): void {
    [, $token] = usersAdminToken();
    $deleted = User::factory()->create();
    $deleted->delete();

    $response = $this->withToken($token)
        ->getJson('/api/v1/admin/users?filter[trashed]=only');

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id');
    expect($ids)->toContain($deleted->id);
});

it('triggers a password reset email for a user', function (): void {
    Notification::fake();
    [, $token] = usersAdminToken();
    $target = User::factory()->create();

    $this->withToken($token)->postJson("/api/v1/admin/users/{$target->id}/password-reset")
        ->assertOk();

    Notification::assertSentTo(
        $target,
        QueuedResetPassword::class
    );
});

it('uploads a user avatar and returns a path + url', function (): void {
    Storage::fake('public');
    [, $token] = usersAdminToken();

    $file = File::image('me.jpg', 120, 120);

    $response = $this->withToken($token)->postJson('/api/v1/admin/users/avatar', [
        'avatar' => $file,
    ]);

    $response->assertOk();
    assertSuccessContract($response);
    $response->assertJsonStructure(['data' => ['path', 'url']]);

    Storage::disk('public')
        ->assertExists($response->json('data.path'));
});

it('rejects a non-image avatar upload', function (): void {
    [, $token] = usersAdminToken();
    $file = File::create('virus.pdf', 10);

    $this->withToken($token)->postJson('/api/v1/admin/users/avatar', [
        'avatar' => $file,
    ])->assertStatus(422);
});

it('exposes is_admin and is_writer flags in the user contract', function (): void {
    [, $token] = usersAdminToken();
    // is_admin مشتقّ من الأدوار؛ is_writer عمود boolean مستقل
    $writer = User::factory()->create(['is_writer' => true]);
    $writer->assignRole('journalist');

    $response = $this->withToken($token)->getJson("/api/v1/admin/users/{$writer->id}");

    $response->assertOk();
    expect($response->json('data.is_admin'))->toBeTrue();
    expect($response->json('data.is_writer'))->toBeTrue();
});

// ─── Security ──────────────────────────────────────────────────────────

it('denies users endpoint without a token', function (): void {
    $this->getJson('/api/v1/admin/users')->assertStatus(401);
});

it('denies a public user-ability token on users endpoint', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $userToken = $admin->createToken('public-token', ['user'])->plainTextToken;

    $this->withToken($userToken)->getJson('/api/v1/admin/users')->assertStatus(403);
});

it('denies an admin lacking users permission', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('reviewer'); // بلا users.view في الـ seeder
    $token = $admin->createToken('admin-token', ['admin'])->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/admin/users')->assertStatus(403);
});

it('returns the unified error contract on validation failure', function (): void {
    [, $token] = usersAdminToken();

    $response = $this->withToken($token)->postJson('/api/v1/admin/users', []);

    $response->assertStatus(422);
    assertErrorContract($response);
    $response->assertJsonStructure(['errors' => ['name', 'email', 'password']]);
});
