<?php

declare(strict_types=1);

use App\Actions\Admin\Advertising\AttachAdPlacementAction;
use App\Actions\Admin\Advertising\DetachAdPlacementAction;
use App\Actions\Admin\Advertising\UpdateAdPlacementAction;
use App\Models\AdCreative;
use App\Models\AdPlacement;
use App\Models\AdZone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::flush());

it('attaches a compatible creative to a zone', function (): void {
    $zone = AdZone::factory()->create(['placement_type' => 'banner']);
    $creative = AdCreative::factory()->create(['type' => 'image']);

    $res = (new AttachAdPlacementAction)->handle([
        'ad_creative_id' => $creative->id,
        'ad_zone_id' => $zone->id,
    ]);

    expect($res->getStatusCode())->toBe(201)
        ->and(
            AdPlacement::where('ad_creative_id', $creative->id)->where('ad_zone_id', $zone->id)->exists()
        )->toBeTrue();
});

it('rejects an incompatible creative type for the zone', function (): void {
    // preroll ⇒ video فقط؛ إبداع صورة غير متوافق.
    $zone = AdZone::factory()->create(['placement_type' => 'preroll']);
    $creative = AdCreative::factory()->create(['type' => 'image']);

    $res = (new AttachAdPlacementAction)->handle([
        'ad_creative_id' => $creative->id,
        'ad_zone_id' => $zone->id,
    ]);

    expect($res->getStatusCode())->toBe(422)
        ->and($res->getData(true)['message'])->toBe(__('ads.placement.incompatible_type'))
        ->and(AdPlacement::count())->toBe(0);
});

it('rejects a duplicate placement for the same creative and zone', function (): void {
    $zone = AdZone::factory()->create(['placement_type' => 'banner']);
    $creative = AdCreative::factory()->create(['type' => 'image']);
    AdPlacement::factory()->create(['ad_creative_id' => $creative->id, 'ad_zone_id' => $zone->id]);

    $res = (new AttachAdPlacementAction)->handle([
        'ad_creative_id' => $creative->id,
        'ad_zone_id' => $zone->id,
    ]);

    expect($res->getStatusCode())->toBe(422)
        ->and($res->getData(true)['message'])->toBe(__('ads.placement.duplicate'))
        ->and(AdPlacement::count())->toBe(1);
});

it('updates placement weight and device targets', function (): void {
    $placement = AdPlacement::factory()->create(['weight' => null]);

    $res = (new UpdateAdPlacementAction)->handle($placement, [
        'weight' => 7,
        'device_targets' => ['mobile'],
    ]);

    expect($res->getStatusCode())->toBe(200)
        ->and($placement->fresh()->weight)->toBe(7)
        ->and($placement->fresh()->device_targets)->toBe(['mobile']);
});

it('detaches a placement with a hard delete', function (): void {
    $placement = AdPlacement::factory()->create();

    $res = (new DetachAdPlacementAction)->handle($placement);

    expect($res->getStatusCode())->toBe(200)
        ->and(AdPlacement::whereKey($placement->id)->exists())->toBeFalse();
});

it('writes an activity-log entry when a placement is attached', function (): void {
    $zone = AdZone::factory()->create(['placement_type' => 'banner']);
    $creative = AdCreative::factory()->create(['type' => 'image']);

    (new AttachAdPlacementAction)->handle([
        'ad_creative_id' => $creative->id,
        'ad_zone_id' => $zone->id,
    ]);

    expect(Activity::where('log_name', 'ad_placement')->where('event', 'created')->exists())->toBeTrue();
});
