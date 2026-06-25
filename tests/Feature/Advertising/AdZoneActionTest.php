<?php

declare(strict_types=1);

use App\Actions\Admin\Advertising\CreateAdZoneAction;
use App\Actions\Admin\Advertising\DeleteAdZoneAction;
use App\Actions\Admin\Advertising\UpdateAdZoneAction;
use App\Models\AdPlacement;
use App\Models\AdZone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::flush());

it('creates an ad zone', function (): void {
    $res = (new CreateAdZoneAction)->handle([
        'key' => 'home_top', 'name' => 'Home Top', 'placement_type' => 'banner',
    ]);

    expect($res->getStatusCode())->toBe(201)
        ->and(AdZone::where('key', 'home_top')->exists())->toBeTrue();
});

it('updates an ad zone and reflects the change', function (): void {
    $zone = AdZone::factory()->create(['key' => 'old_key', 'is_active' => true]);

    $res = (new UpdateAdZoneAction)->handle($zone, ['key' => 'new_key', 'is_active' => false]);

    expect($res->getStatusCode())->toBe(200)
        ->and($zone->fresh()->key)->toBe('new_key')
        ->and($zone->fresh()->is_active)->toBeFalse();
});

it('blocks hard-deleting a zone that still has placements', function (): void {
    $zone = AdZone::factory()->create();
    AdPlacement::factory()->create(['ad_zone_id' => $zone->id]);

    $res = (new DeleteAdZoneAction)->handle($zone);

    expect($res->getStatusCode())->toBe(422)
        ->and(AdZone::whereKey($zone->id)->exists())->toBeTrue();
});

it('hard-deletes a zone with no placements', function (): void {
    $zone = AdZone::factory()->create();

    $res = (new DeleteAdZoneAction)->handle($zone);

    expect($res->getStatusCode())->toBe(200)
        ->and(AdZone::whereKey($zone->id)->exists())->toBeFalse();
});
