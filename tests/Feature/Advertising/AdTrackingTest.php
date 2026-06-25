<?php

declare(strict_types=1);

use App\Enums\AdEventType;
use App\Models\AdCampaign;
use App\Models\AdCounter;
use App\Models\AdCreative;
use App\Models\AdPlacement;
use App\Models\AdStatDaily;
use App\Models\AdZone;
use App\Support\Advertising\AdEventBuffer;
use App\Support\Advertising\AdTracker;
use App\Support\Engagement\EngagementActor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::flush()); // نظّف مخزن التتبّع/الإزالة (array) بين التأكيدات.

function adTrackPlacement(): AdPlacement
{
    $zone = AdZone::factory()->create();
    $campaign = AdCampaign::factory()->create();
    $creative = AdCreative::factory()->create(['ad_campaign_id' => $campaign->id]);

    return AdPlacement::factory()->create(['ad_creative_id' => $creative->id, 'ad_zone_id' => $zone->id]);
}

it('buffers an impression then flushes into counter + daily with derived dimensions', function (): void {
    $p = adTrackPlacement();
    $actor = EngagementActor::guest('visitor-1');

    expect(AdTracker::record(AdEventType::Impression, $p->id, $actor, 'direct', 100))->toBeTrue();
    expect(AdEventBuffer::flush())->toBe(1);

    $counter = AdCounter::where('ad_placement_id', $p->id)->first();
    expect($counter->impressions)->toBe(1)->and($counter->clicks)->toBe(0);

    $daily = AdStatDaily::where('ad_placement_id', $p->id)->first();
    expect($daily)->not->toBeNull()
        ->and($daily->impressions)->toBe(1)
        ->and($daily->impressions_direct)->toBe(1)
        ->and((int) $daily->ad_zone_id)->toBe((int) $p->ad_zone_id)
        ->and((int) $daily->ad_creative_id)->toBe((int) $p->ad_creative_id)
        ->and((int) $daily->ad_campaign_id)->toBe((int) $p->creative->ad_campaign_id);
});

it('deduplicates repeated impressions within the same bucket', function (): void {
    $p = adTrackPlacement();
    $actor = EngagementActor::guest('visitor-1');

    expect(AdTracker::record(AdEventType::Impression, $p->id, $actor, 'direct', 100))->toBeTrue()
        ->and(AdTracker::record(AdEventType::Impression, $p->id, $actor, 'direct', 100))->toBeFalse();
    AdEventBuffer::flush();

    expect(AdCounter::where('ad_placement_id', $p->id)->value('impressions'))->toBe(1);
});

it('counts the same actor again in a later bucket', function (): void {
    $p = adTrackPlacement();
    $actor = EngagementActor::guest('visitor-1');

    AdTracker::record(AdEventType::Impression, $p->id, $actor, 'direct', 100);
    AdTracker::record(AdEventType::Impression, $p->id, $actor, 'direct', 101);
    AdEventBuffer::flush();

    expect(AdCounter::where('ad_placement_id', $p->id)->value('impressions'))->toBe(2);
});

it('filters bot actors out of tracking', function (): void {
    $p = adTrackPlacement();
    $req = Request::create('/', 'POST');
    $req->headers->set('User-Agent', 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)');
    $bot = EngagementActor::fromRequest($req);

    expect($bot->isBot)->toBeTrue()
        ->and(AdTracker::record(AdEventType::Impression, $p->id, $bot, 'direct', 100))->toBeFalse()
        ->and(AdEventBuffer::flush())->toBe(0);
});

it('records clicks separately from impressions', function (): void {
    $p = adTrackPlacement();
    $actor = EngagementActor::guest('visitor-1');

    AdTracker::record(AdEventType::Click, $p->id, $actor, 'direct', 100);
    AdEventBuffer::flush();

    $counter = AdCounter::where('ad_placement_id', $p->id)->first();
    expect($counter->clicks)->toBe(1)->and($counter->impressions)->toBe(0);
});
