<?php

declare(strict_types=1);

use App\Actions\Admin\Advertising\AdAnalyticsAction;
use App\Models\AdCampaign;
use App\Models\AdCreative;
use App\Models\AdStatDaily;
use App\Models\AdZone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::flush());

function seedStat(array $overrides = []): AdStatDaily
{
    return AdStatDaily::create(array_merge([
        'ad_placement_id' => 1,
        'ad_zone_id' => 1,
        'ad_creative_id' => 1,
        'ad_campaign_id' => 1,
        'day' => now()->toDateString(),
        'impressions' => 0,
        'clicks' => 0,
        'impressions_direct' => 0,
        'impressions_internal' => 0,
        'impressions_search' => 0,
        'impressions_social' => 0,
        'impressions_referral' => 0,
    ], $overrides));
}

it('aggregates impressions, clicks, ctr, channels and a zero-filled daily trend', function (): void {
    $zone = AdZone::factory()->create(['name' => 'Home Top']);
    $campaign = AdCampaign::factory()->create(['name' => 'Summer']);
    $creative = AdCreative::factory()->create(['ad_campaign_id' => $campaign->id, 'title' => 'Banner A']);

    seedStat([
        'ad_zone_id' => $zone->id, 'ad_creative_id' => $creative->id, 'ad_campaign_id' => $campaign->id,
        'day' => now()->toDateString(), 'impressions' => 100, 'clicks' => 5,
        'impressions_direct' => 60, 'impressions_internal' => 10, 'impressions_search' => 20,
        'impressions_social' => 5, 'impressions_referral' => 5,
    ]);
    seedStat([
        'ad_zone_id' => $zone->id, 'ad_creative_id' => $creative->id, 'ad_campaign_id' => $campaign->id,
        'day' => now()->subDay()->toDateString(), 'impressions' => 50, 'clicks' => 5,
        'impressions_direct' => 50,
    ]);

    $res = (new AdAnalyticsAction)->handle(Request::create('/x', 'GET', ['range' => '30d']));
    $payload = $res->getData(true)['data'];

    expect($payload['totals']['impressions'])->toBe(150)
        ->and($payload['totals']['clicks'])->toBe(10)
        ->and($payload['totals']['ctr'])->toBe(6.67) // 10/150*100 = 6.666… → 6.67
        ->and($payload['channels']['direct'])->toBe(110)
        ->and($payload['channels']['search'])->toBe(20)
        ->and($payload['top_campaigns'][0]['name'])->toBe('Summer')
        ->and($payload['top_campaigns'][0]['impressions'])->toBe(150)
        ->and($payload['top_creatives'][0]['name'])->toBe('Banner A')
        ->and($payload['top_zones'][0]['name'])->toBe('Home Top')
        ->and($payload['trend']['points'])->toHaveCount(30)
        ->and($payload['window']['range'])->toBe('30d');
});

it('returns zeroed totals for an empty window', function (): void {
    $res = (new AdAnalyticsAction)->handle(Request::create('/x', 'GET', ['range' => '7d']));
    $payload = $res->getData(true)['data'];

    expect($payload['totals']['impressions'])->toBe(0)
        ->and($payload['totals']['ctr'])->toBe(0) // JSON يُسلسِل 0.0 كـ 0
        ->and($payload['top_campaigns'])->toBe([])
        ->and($payload['trend']['points'])->toHaveCount(7);
});
