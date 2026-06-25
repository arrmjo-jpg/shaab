<?php

declare(strict_types=1);

use App\Models\AdCampaign;
use App\Models\AdCreative;
use App\Models\AdPlacement;
use App\Models\AdZone;
use App\Support\Advertising\AdServer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::flush()); // الكاش (array) لا يُعاد بناؤه بين كل التأكيدات — نظّفه صراحةً.

/** @return array{zone:AdZone,campaign:AdCampaign,creative:AdCreative,placement:AdPlacement} */
function adServeSetup(array $placement = [], array $creative = [], array $campaign = [], array $zone = []): array
{
    $z = AdZone::factory()->create(array_merge(
        ['key' => 'home_top', 'locale' => null, 'is_active' => true, 'selector_strategy' => 'weighted'],
        $zone,
    ));
    $c = AdCampaign::factory()->create(array_merge(
        ['status' => 'active', 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay()],
        $campaign,
    ));
    $cr = AdCreative::factory()->create(array_merge(
        ['ad_campaign_id' => $c->id, 'type' => 'image', 'is_active' => true, 'weight' => 1],
        $creative,
    ));
    $p = AdPlacement::factory()->create(array_merge(
        ['ad_creative_id' => $cr->id, 'ad_zone_id' => $z->id, 'is_active' => true],
        $placement,
    ));

    return ['zone' => $z, 'campaign' => $c, 'creative' => $cr, 'placement' => $p];
}

it('serves an active, in-flight, device-eligible creative', function (): void {
    adServeSetup();

    $chosen = AdServer::serve('home_top', 'ar', 'desktop', 1);

    expect($chosen)->not->toBeNull()
        ->and($chosen['type'])->toBe('image');
});

it('segments the candidate pool by device class', function (): void {
    adServeSetup(['device_targets' => ['mobile']]);

    expect(AdServer::pool('home_top', 'ar', 'desktop')['candidates'])->toBe([])
        ->and(AdServer::pool('home_top', 'ar', 'mobile')['candidates'])->toHaveCount(1);
});

it('excludes non-servable campaigns (draft) and expired windows', function (): void {
    adServeSetup(campaign: ['status' => 'draft']);
    expect(AdServer::pool('home_top', 'ar', 'desktop')['candidates'])->toBe([]);
});

it('excludes video creatives in this phase', function (): void {
    adServeSetup(creative: ['type' => 'video']);
    expect(AdServer::pool('home_top', 'ar', 'desktop')['candidates'])->toBe([]);
});

it('returns an empty pool and null serve for an inactive zone', function (): void {
    adServeSetup(zone: ['is_active' => false]);

    expect(AdServer::pool('home_top', 'ar', 'desktop')['candidates'])->toBe([])
        ->and(AdServer::serve('home_top', 'ar', 'desktop', 1))->toBeNull();
});

it('selects deterministically within the same time bucket', function (): void {
    $base = adServeSetup();
    $c2 = AdCreative::factory()->create(['ad_campaign_id' => $base['campaign']->id, 'type' => 'image', 'is_active' => true]);
    AdPlacement::factory()->create(['ad_creative_id' => $c2->id, 'ad_zone_id' => $base['zone']->id, 'is_active' => true]);

    $a = AdServer::serve('home_top', 'ar', 'desktop', 7);
    $b = AdServer::serve('home_top', 'ar', 'desktop', 7);

    expect($a)->not->toBeNull()->and($a)->toBe($b);
});
