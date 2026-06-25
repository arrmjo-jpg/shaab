<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
});

function failedJobsToken(string $role = 'super_admin'): string
{
    $admin = User::factory()->create();
    $admin->assignRole($role);

    return $admin->createToken('admin-token', ['admin'])->plainTextToken;
}

/** يسجّل مهمة فاشلة عبر المزوّد الرسمي ويُرجع الـ UUID. */
function logFailedJob(string $name = 'App\\Jobs\\TranscodeVideoAssetJob', string $queue = 'media'): string
{
    $payload = json_encode([
        'uuid' => (string) Str::uuid(),
        'displayName' => $name,
        'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
        'maxTries' => 3,
        // أمر مُسلسَل صالح (stdClass) كي يقبله queue:retry دون فكّ تشفير.
        'data' => ['commandName' => $name, 'command' => 'O:8:"stdClass":0:{}'],
    ]);

    return app('queue.failer')->log('database', $queue, $payload, new RuntimeException('boom: signature mismatch'));
}

// ─── List ─────────────────────────────────────────────────────────────────

it('lists failed jobs with parsed metadata', function (): void {
    $token = failedJobsToken();
    logFailedJob();

    $this->withToken($token)->getJson('/api/v1/admin/system/failed-jobs')
        ->assertOk()
        ->assertJsonPath('data.meta.total', 1)
        ->assertJsonPath('data.data.0.queue', 'media')
        ->assertJsonPath('data.data.0.name', 'App\\Jobs\\TranscodeVideoAssetJob')
        ->assertJsonPath('data.data.0.max_tries', 3);
});

it('search filters failed jobs by queue/name/exception', function (): void {
    $token = failedJobsToken();
    logFailedJob('App\\Jobs\\MirrorMediaToRemoteJob', 'media');
    logFailedJob('App\\Jobs\\SendEmailJob', 'default');

    $this->withToken($token)->getJson('/api/v1/admin/system/failed-jobs?q=Mirror')
        ->assertOk()
        ->assertJsonPath('data.meta.total', 1)
        ->assertJsonPath('data.data.0.name', 'App\\Jobs\\MirrorMediaToRemoteJob');
});

// ─── Retry ──────────────────────────────────────────────────────────────────

it('retries a selected failed job and removes it from the failed list', function (): void {
    $token = failedJobsToken();
    $uuid = logFailedJob();

    $this->withToken($token)->postJson('/api/v1/admin/system/failed-jobs/retry', [
        'ids' => [$uuid],
    ])->assertOk();

    expect(app('queue.failer')->find($uuid))->toBeNull();
});

it('retry-all is a safe no-op when there are no failed jobs', function (): void {
    $token = failedJobsToken();

    $this->withToken($token)->postJson('/api/v1/admin/system/failed-jobs/retry', [
        'all' => true,
    ])->assertOk();
});

it('rejects retry with neither ids nor all', function (): void {
    $token = failedJobsToken();

    $this->withToken($token)->postJson('/api/v1/admin/system/failed-jobs/retry', [])
        ->assertStatus(422);
});

// ─── Delete ──────────────────────────────────────────────────────────────────

it('deletes selected failed jobs', function (): void {
    $token = failedJobsToken();
    $uuid = logFailedJob();

    $this->withToken($token)->postJson('/api/v1/admin/system/failed-jobs/delete', [
        'ids' => [$uuid],
    ])->assertOk();

    expect(app('queue.failer')->find($uuid))->toBeNull();
});

it('bulk-deletes all failed jobs', function (): void {
    $token = failedJobsToken();
    logFailedJob();
    logFailedJob('App\\Jobs\\Other', 'default');

    $this->withToken($token)->postJson('/api/v1/admin/system/failed-jobs/delete', [
        'all' => true,
    ])->assertOk();

    expect(app('queue.failer')->all())->toBeEmpty();
});

// ─── Security ────────────────────────────────────────────────────────────────

it('denies access without a token', function (): void {
    $this->getJson('/api/v1/admin/system/failed-jobs')->assertStatus(401);
});

it('denies management for an admin lacking failed_jobs.manage', function (): void {
    $token = failedJobsToken('reviewer');

    $this->withToken($token)->postJson('/api/v1/admin/system/failed-jobs/delete', ['all' => true])
        ->assertStatus(403);
});
