<?php

declare(strict_types=1);

use App\Actions\Admin\Broadcast\SyncBroadcastViewerCountsAction;
use App\Enums\EngagementType;
use App\Models\Broadcast;
use App\Models\BroadcastViewerSample;
use App\Models\ContentDailyStat;
use App\Models\EngagementCounter;
use App\Support\Broadcast\BroadcastPresence;
use App\Support\Engagement\DailyEngagementRollup;
use App\Support\Engagement\EngagementActor;
use App\Support\Engagement\EngagementService;
use App\Support\Engagement\ViewBuffer;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── Daily engagement rollup (forward-only telemetry) ────────────────────────

it('rolls up daily views with channel breakdown and accumulates in today\'s row', function (): void {
    $type = 'App\\Models\\Video';

    DailyEngagementRollup::addViews($type, 7, 2, ['search' => 2]);
    DailyEngagementRollup::addViews($type, 7, 1, ['social' => 1]);

    $row = ContentDailyStat::query()->where('engageable_type', $type)->where('engageable_id', 7)->first();

    expect($row)->not->toBeNull();
    expect($row->day->toDateString())->toBe(now()->toDateString());
    expect((int) $row->views)->toBe(3);
    expect((int) $row->views_search)->toBe(2);
    expect((int) $row->views_social)->toBe(1);
    expect((int) $row->views_direct)->toBe(0);
});

it('records signed daily reaction deltas (negative on toggle-off)', function (): void {
    $type = 'App\\Models\\Video';

    DailyEngagementRollup::addReactionDeltas($type, 7, 1, 0, 0);
    DailyEngagementRollup::addReactionDeltas($type, 7, -1, 1, 0);

    $row = ContentDailyStat::query()->where('engageable_id', 7)->first();

    expect((int) $row->likes)->toBe(0);     // +1 then -1
    expect((int) $row->dislikes)->toBe(1);  // +1
});

it('is a no-op for zero/empty deltas', function (): void {
    DailyEngagementRollup::addViews('App\\Models\\Video', 9, 0, ['search' => 5]);
    DailyEngagementRollup::addReactionDeltas('App\\Models\\Video', 9, 0, 0, 0);

    expect(ContentDailyStat::query()->where('engageable_id', 9)->exists())->toBeFalse();
});

it('writes the daily rollup with channel split when the view buffer flushes', function (): void {
    config(['performance.view_buffer.enabled' => true]);
    expect(ViewBuffer::supported())->toBeTrue();

    $type = 'App\\Models\\Video';
    ViewBuffer::add($type, 5, 'search');
    ViewBuffer::add($type, 5, 'search');
    ViewBuffer::add($type, 5, 'social');

    expect(ViewBuffer::flush())->toBe(1);

    $row = ContentDailyStat::query()->where('engageable_id', 5)->first();
    expect((int) $row->views)->toBe(3);
    expect((int) $row->views_search)->toBe(2);
    expect((int) $row->views_social)->toBe(1);

    // العدّاد التراكمي تحدّث أيضاً (نفس التفريغ).
    expect((int) EngagementCounter::query()->where('engageable_id', 5)->value('views'))->toBe(3);
});

it('records daily reaction deltas through the engagement service (toggle nets correctly)', function (): void {
    $broadcast = Broadcast::factory()->create();
    $service = app(EngagementService::class);
    $actor = EngagementActor::guest('reactor-1');

    $service->react($broadcast, $actor, EngagementType::Like);
    $morph = $broadcast->getMorphClass();
    $row = ContentDailyStat::query()->where('engageable_type', $morph)->where('engageable_id', $broadcast->id)->first();
    expect((int) $row->likes)->toBe(1);

    // تبديل like→dislike في نفس اليوم: صافي likes=0، dislikes=1.
    $service->react($broadcast, $actor, EngagementType::Dislike);
    $row->refresh();
    expect((int) $row->likes)->toBe(0);
    expect((int) $row->dislikes)->toBe(1);
});

// ─── Broadcast viewer samples + peak (forward-only telemetry) ────────────────

it('samples live viewer counts and tracks the all-time peak', function (): void {
    $broadcast = Broadcast::factory()->live()->create();

    BroadcastPresence::touch($broadcast->id, 'u1');
    BroadcastPresence::touch($broadcast->id, 'u2');
    expect(BroadcastPresence::count($broadcast->id))->toBe(2);

    (new SyncBroadcastViewerCountsAction)->handle();

    $sample = BroadcastViewerSample::query()->where('broadcast_id', $broadcast->id)->latest('id')->first();
    expect($sample)->not->toBeNull();
    expect((int) $sample->viewers)->toBe(2);

    $broadcast->refresh();
    expect((int) $broadcast->viewer_count)->toBe(2);
    expect((int) $broadcast->peak_viewer_count)->toBe(2);
});

it('does not sample non-live broadcasts', function (): void {
    $broadcast = Broadcast::factory()->create(); // draft

    (new SyncBroadcastViewerCountsAction)->handle();

    expect(BroadcastViewerSample::query()->where('broadcast_id', $broadcast->id)->exists())->toBeFalse();
});
