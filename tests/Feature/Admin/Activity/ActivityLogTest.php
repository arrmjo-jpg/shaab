<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
});

function activityAdmin(): array
{
    $a = User::factory()->create();
    $a->assignRole('super_admin');

    return [$a, $a->createToken('admin', ['admin'])->plainTextToken];
}

it('auto-records a model change and lists it (sanitized)', function (): void {
    [, $token] = activityAdmin();
    $target = User::factory()->create(['is_writer' => false]);

    $this->withToken($token)
        ->putJson("/api/v1/admin/users/{$target->id}", ['is_writer' => true])
        ->assertOk();

    $res = $this->withToken($token)
        ->getJson('/api/v1/admin/activity?filter[log_name]=user');

    $res->assertOk();
    assertSuccessContract($res);
    $res->assertJsonStructure([
        'data' => [['id', 'log_name', 'event', 'description', 'changes', 'created_at']],
        'meta' => ['pagination' => ['total']],
    ]);
    expect(collect($res->json('data'))->pluck('log_name'))->each->toBe('user');
});

it('never leaks sensitive values in changes/context', function (): void {
    [$admin, $token] = activityAdmin();

    activity('test')
        ->causedBy($admin)
        ->withProperties(['attributes' => ['password' => 'PLAINTEXT', 'name' => 'x']])
        ->log('secret check');

    $row = collect(
        $this->withToken($token)->getJson('/api/v1/admin/activity?filter[log_name]=test')->json('data')
    )->first();

    expect($row['changes']['attributes']['password'])->toBe('••••••');
    expect($row['changes']['attributes']['name'])->toBe('x');
});

it('records settings updates as a settings activity (keys only, no secrets)', function (): void {
    [, $token] = activityAdmin();

    $this->withToken($token)->putJson('/api/v1/admin/settings/general', [
        'site_name' => 'AlphaCMS QA',
        'site_email' => 'qa@alpha.test',
        'timezone' => 'UTC',
    ])->assertOk();

    $row = collect(
        $this->withToken($token)->getJson('/api/v1/admin/activity?filter[log_name]=settings')->json('data')
    )->first();

    expect($row)->not->toBeNull();
    expect($row['event'])->toBe('updated');
    expect($row['context']['changed'])->toContain('site_name');
});

it('filters by causer using the composite (type + id) path', function (): void {
    [$admin, $token] = activityAdmin();
    $other = User::factory()->create();

    activity('test')->causedBy($admin)->log('mine');
    activity('test')->causedBy($other)->log('theirs');

    $rows = collect(
        $this->withToken($token)
            ->getJson("/api/v1/admin/activity?filter[causer]={$admin->id}&filter[log_name]=test")
            ->json('data')
    );

    expect($rows)->toHaveCount(1);
    expect($rows->first()['description'])->toBe('mine');
});

it('filters by a sargable created_at range', function (): void {
    [$admin, $token] = activityAdmin();
    $old = activity('test')->causedBy($admin)->log('old one');
    Activity::query()
        ->latest('id')->first()
        ->forceFill(['created_at' => now()->subDays(10)])->save();
    activity('test')->causedBy($admin)->log('fresh one');

    $today = now()->toDateString();
    $rows = collect(
        $this->withToken($token)
            ->getJson("/api/v1/admin/activity?filter[from]={$today}&filter[log_name]=test")
            ->json('data')
    );

    expect($rows->pluck('description'))->toContain('fresh one');
    expect($rows->pluck('description'))->not->toContain('old one');
    unset($old);
});

it('filters by event', function (): void {
    [, $token] = activityAdmin();
    $u = User::factory()->create(['is_writer' => false]);

    $this->withToken($token)
        ->putJson("/api/v1/admin/users/{$u->id}", ['is_writer' => true])
        ->assertOk();

    $rows = collect(
        $this->withToken($token)
            ->getJson('/api/v1/admin/activity?filter[event]=updated&filter[log_name]=user')
            ->json('data')
    );

    expect($rows)->not->toBeEmpty();
    expect($rows->pluck('event'))->each->toBe('updated');
});

it('denies activity log to an admin lacking the permission', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('editor'); // بلا activity.view
    $token = $admin->createToken('admin', ['admin'])->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/admin/activity')->assertStatus(403);
});

it('denies activity log without a token', function (): void {
    $this->getJson('/api/v1/admin/activity')->assertStatus(401);
});
