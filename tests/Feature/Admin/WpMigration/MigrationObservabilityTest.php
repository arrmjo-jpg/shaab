<?php

declare(strict_types=1);

use App\Enums\MigrationRunStatus;
use App\Jobs\WpMigration\DispatchMigrationChunkJob;
use App\Models\MigrationItem;
use App\Models\MigrationRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
    Queue::fake();
});

function obsRun(string $status = 'running', ?Carbon $startedAt = null, ?Carbon $finishedAt = null): MigrationRun
{
    return MigrationRun::create([
        'name' => 'shaab', 'status' => $status, 'db_host' => '127.0.0.1', 'db_name' => 'shaab',
        'db_username' => 'root', 'db_password' => 'x', 'table_prefix' => '',
        'started_at' => $startedAt, 'finished_at' => $finishedAt,
    ]);
}

/**
 * @param  array<string,mixed>  $attrs
 */
function obsItem(MigrationRun $run, int $wpId, string $status, array $attrs = []): MigrationItem
{
    return MigrationItem::create(array_merge([
        'run_id' => $run->id, 'wp_post_id' => $wpId, 'status' => $status,
    ], $attrs));
}

function obsToken(string ...$roles): string
{
    $u = User::factory()->create();
    foreach ($roles as $r) {
        $u->assignRole($r);
    }

    return $u->createToken('admin', ['admin'])->plainTextToken;
}

/** تشغيلة بمزيج حالات كامل + وسائط لتمارين اللوحة. */
function obsSeeded(string $status = 'running', ?Carbon $startedAt = null): MigrationRun
{
    $run = obsRun($status, $startedAt ?? now()->subMinutes(10));

    obsItem($run, 1, 'done', ['media_imported' => 2, 'media_reused' => 1, 'source_title' => 'خبر ١']);
    obsItem($run, 2, 'done', ['media_imported' => 2, 'media_reused' => 1, 'source_title' => 'خبر ٢']);
    obsItem($run, 3, 'done', ['media_imported' => 2, 'media_reused' => 1, 'source_title' => 'خبر ٣']);
    obsItem($run, 4, 'partial', ['media_imported' => 1, 'media_failed' => 1, 'source_title' => 'جزئي', 'flags' => ['warnings' => ['media_unresolved']]]);
    obsItem($run, 5, 'failed', ['attempts' => 3, 'source_title' => 'فاشل أ', 'last_error' => 'persist_failed: boom', 'flags' => ['reason' => 'persist_failed']]);
    obsItem($run, 6, 'failed', ['attempts' => 1, 'source_title' => null, 'flags' => ['reason' => 'source_read_failed']]);
    obsItem($run, 7, 'skipped', ['source_title' => 'متخطّى', 'flags' => ['reason' => 'category_type_conflict']]);
    obsItem($run, 8, 'processing', ['source_title' => 'يُعالَج']);
    obsItem($run, 9, 'queued');
    obsItem($run, 10, 'pending');

    return $run;
}

// ─── Dashboard observability (#1) ─────────────────────────────────────────────

it('reports a live status breakdown across all buckets (#1)', function (): void {
    $run = obsSeeded();

    $res = $this->withToken(obsToken('super_admin'))
        ->getJson("/api/v1/admin/wp-migration/runs/{$run->id}/stats")
        ->assertOk();

    $res->assertJsonPath('data.counts.total', 10)
        ->assertJsonPath('data.counts.pending', 1)
        ->assertJsonPath('data.counts.queued', 1)
        ->assertJsonPath('data.counts.processing', 1)
        ->assertJsonPath('data.counts.done', 3)
        ->assertJsonPath('data.counts.partial', 1)
        ->assertJsonPath('data.counts.failed', 2)
        ->assertJsonPath('data.counts.skipped', 1)
        ->assertJsonPath('data.status', 'running');
});

// ─── Performance metrics (#2) ─────────────────────────────────────────────────

it('computes elapsed, throughput, ETA and percent (#2)', function (): void {
    $run = obsSeeded('running', now()->subMinutes(10));

    $res = $this->withToken(obsToken('super_admin'))
        ->getJson("/api/v1/admin/wp-migration/runs/{$run->id}/stats")
        ->assertOk();

    // processed = done+partial+failed+skipped = 7/10 ⇒ 70%.
    expect((float) $res->json('data.performance.percent'))->toBe(70.0);

    $perf = $res->json('data.performance');
    expect($perf['elapsed_seconds'])->toBeGreaterThan(300);
    expect($perf['throughput_per_min'])->toBeGreaterThan(0);
    expect($perf['eta_seconds'])->not->toBeNull(); // جارية + عمل متبقٍّ
});

it('omits ETA when the run is not running', function (): void {
    $run = obsSeeded('completed', now()->subMinutes(10));
    $run->forceFill(['finished_at' => now()])->save();

    $this->withToken(obsToken('super_admin'))
        ->getJson("/api/v1/admin/wp-migration/runs/{$run->id}/stats")
        ->assertOk()
        ->assertJsonPath('data.performance.eta_seconds', null);
});

// ─── Media metrics (#3) ───────────────────────────────────────────────────────

it('aggregates media imported, reused and failed (#3)', function (): void {
    $run = obsSeeded();

    $this->withToken(obsToken('super_admin'))
        ->getJson("/api/v1/admin/wp-migration/runs/{$run->id}/stats")
        ->assertOk()
        ->assertJsonPath('data.media.imported', 7) // 2+2+2 (done) + 1 (partial)
        ->assertJsonPath('data.media.reused', 3)   // 1+1+1
        ->assertJsonPath('data.media.failed', 1);  // partial
});

// ─── Failure inspection + filters (#4/#5) ─────────────────────────────────────

it('lists failed items with full drill-down detail (#4)', function (): void {
    $run = obsSeeded();

    $res = $this->withToken(obsToken('super_admin'))
        ->getJson("/api/v1/admin/wp-migration/runs/{$run->id}/items?status=failed")
        ->assertOk()
        ->assertJsonPath('meta.pagination.total', 2);

    $row = collect($res->json('data'))->firstWhere('wp_post_id', 5);
    expect($row['source_title'])->toBe('فاشل أ');
    expect($row['failure_reason'])->toBe('persist_failed');
    expect($row['attempts'])->toBe(3);
    expect($row['last_error'])->toBe('persist_failed: boom');
    expect($row)->toHaveKeys(['checkpoints', 'media', 'warnings', 'status']);
});

it('filters the ledger by partial, skipped and processing (#5)', function (): void {
    $run = obsSeeded();
    $token = obsToken('super_admin');

    foreach (['partial' => 1, 'skipped' => 1, 'processing' => 1] as $status => $count) {
        $this->withToken($token)
            ->getJson("/api/v1/admin/wp-migration/runs/{$run->id}/items?status={$status}")
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', $count);
    }
});

// ─── Final report (#7) ────────────────────────────────────────────────────────

it('produces a closure report with success rate and failure breakdown (#7)', function (): void {
    $run = obsSeeded('completed', now()->subMinutes(5));
    $run->forceFill(['finished_at' => now()])->save();

    $res = $this->withToken(obsToken('super_admin'))
        ->getJson("/api/v1/admin/wp-migration/runs/{$run->id}/report")
        ->assertOk();

    $res->assertJsonPath('data.is_complete', true)
        ->assertJsonPath('data.succeeded', 4); // done(3)+partial(1)
    expect((float) $res->json('data.success_rate'))->toBe(40.0); // 4/10

    $failures = collect($res->json('data.failures'));
    expect($failures->firstWhere('reason', 'persist_failed')['count'])->toBe(1);
    expect($failures->firstWhere('reason', 'source_read_failed')['count'])->toBe(1);
    expect($failures->firstWhere('reason', 'category_type_conflict')['count'])->toBe(1);
});

// ─── Retry actions (#6) ───────────────────────────────────────────────────────

it('retries all failed items and reopens a completed run (#6)', function (): void {
    $run = obsSeeded('completed', now()->subMinutes(5));
    $run->forceFill(['finished_at' => now()])->save();

    $this->withToken(obsToken('super_admin'))
        ->postJson("/api/v1/admin/wp-migration/runs/{$run->id}/retry", ['mode' => 'failed'])
        ->assertOk()
        ->assertJsonPath('meta.retried', 2);

    expect($run->fresh()->status)->toBe(MigrationRunStatus::Running);
    expect($run->fresh()->finished_at)->toBeNull();
    expect(MigrationItem::query()->where('run_id', $run->id)->where('status', 'failed')->count())->toBe(0);
    expect(MigrationItem::query()->where('run_id', $run->id)->where('wp_post_id', 5)->first()->attempts)->toBe(0);
    Queue::assertPushed(DispatchMigrationChunkJob::class, 1);
});

it('retries all partial items (#6)', function (): void {
    $run = obsSeeded();

    $this->withToken(obsToken('super_admin'))
        ->postJson("/api/v1/admin/wp-migration/runs/{$run->id}/retry", ['mode' => 'partial'])
        ->assertOk()
        ->assertJsonPath('meta.retried', 1);

    expect(MigrationItem::query()->where('run_id', $run->id)->where('wp_post_id', 4)->first()->status->value)->toBe('pending');
});

it('retries only the selected eligible items (#6)', function (): void {
    $run = obsSeeded();
    $failed = MigrationItem::query()->where('run_id', $run->id)->where('wp_post_id', 5)->first();
    $done = MigrationItem::query()->where('run_id', $run->id)->where('wp_post_id', 1)->first();

    $this->withToken(obsToken('super_admin'))
        ->postJson("/api/v1/admin/wp-migration/runs/{$run->id}/retry", [
            'mode' => 'selected', 'ids' => [$failed->id, $done->id],
        ])
        ->assertOk()
        ->assertJsonPath('meta.retried', 1); // done غير مؤهَّل لإعادة المحاولة

    expect($failed->fresh()->status->value)->toBe('pending');
    expect($done->fresh()->status->value)->toBe('done');
});

it('returns 422 when nothing matches the retry request (#6)', function (): void {
    $run = obsSeeded();

    $this->withToken(obsToken('super_admin'))
        ->postJson("/api/v1/admin/wp-migration/runs/{$run->id}/retry", [
            'mode' => 'selected', 'ids' => [999999],
        ])
        ->assertStatus(422);
});

// ─── Timeline (#8) ────────────────────────────────────────────────────────────

it('records lifecycle events on the timeline (#8)', function (): void {
    $run = obsRun('running', now());
    $token = obsToken('super_admin');

    $this->withToken($token)->postJson("/api/v1/admin/wp-migration/runs/{$run->id}/pause")->assertOk();
    $this->withToken($token)->postJson("/api/v1/admin/wp-migration/runs/{$run->id}/resume")->assertOk();
    $this->withToken($token)->postJson("/api/v1/admin/wp-migration/runs/{$run->id}/stop")->assertOk();

    $events = collect($run->fresh()->timeline)->pluck('event')->all();
    expect($events)->toBe(['paused', 'resumed', 'stopping']);
});

it('records completion on the timeline when the run finishes (#8)', function (): void {
    $run = obsRun('running', now());
    obsItem($run, 1, 'done');

    (new DispatchMigrationChunkJob($run->id))->handle();

    $events = collect($run->fresh()->timeline)->pluck('event')->all();
    expect($events)->toContain('completed');
});

// ─── RBAC ─────────────────────────────────────────────────────────────────────

// طلب واحد لكل اختبار: خلط مستخدمَين في طلبين يُبقي مُستخدِم الطلب الأول مُخزَّناً.
it('requires wp-migration.manage to retry (manage-gated)', function (): void {
    $run = obsSeeded();

    $this->withToken(obsToken('editor'))
        ->postJson("/api/v1/admin/wp-migration/runs/{$run->id}/retry", ['mode' => 'failed'])
        ->assertStatus(403);
});

it('allows an authorized reader to read the dashboard stats', function (): void {
    $run = obsSeeded();

    $this->withToken(obsToken('super_admin'))
        ->getJson("/api/v1/admin/wp-migration/runs/{$run->id}/stats")
        ->assertOk();
});
