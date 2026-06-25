<?php

declare(strict_types=1);

use App\Models\AdCampaign;
use App\Models\AdCounter;
use App\Models\AdCreative;
use App\Models\AdPlacement;
use App\Models\AdZone;
use App\Support\Advertising\AdBeaconToken;
use App\Support\Advertising\AdBucket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Cache::flush();
    // احتساب مباشر حتميّ في الاختبار (تعطيل التجميع المؤقّت).
    config(['advertising.tracking.buffer_enabled' => false]);
});

function trackablePlacement(): AdPlacement
{
    $zone = AdZone::factory()->create(['placement_type' => 'banner']);
    $campaign = AdCampaign::factory()->create();
    $creative = AdCreative::factory()->html()->create([
        'ad_campaign_id' => $campaign->id,
        'landing_url' => 'https://advertiser.test/go',
    ]);

    return AdPlacement::factory()->create([
        'ad_creative_id' => $creative->id,
        'ad_zone_id' => $zone->id,
    ]);
}

it('records an impression for a valid beacon token', function (): void {
    $p = trackablePlacement();
    $token = AdBeaconToken::issue($p->id, (int) $p->ad_zone_id, AdBucket::current());

    $res = $this->postJson('/api/v1/ads/track/impression', ['token' => $token]);

    $res->assertOk();
    expect($res->json('data.accepted'))->toBeTrue()
        ->and($res->headers->get('Cache-Control'))->toContain('no-store')
        ->and((int) AdCounter::where('ad_placement_id', $p->id)->value('impressions'))->toBe(1);
});

it('rejects an invalid impression token', function (): void {
    $res = $this->postJson('/api/v1/ads/track/impression', ['token' => 'bogus.signature']);

    $res->assertStatus(422);
    expect(AdCounter::count())->toBe(0);
});

it('does not count an impression from a bot user-agent but still accepts', function (): void {
    $p = trackablePlacement();
    $token = AdBeaconToken::issue($p->id, (int) $p->ad_zone_id, AdBucket::current());

    $res = $this->withHeaders(['User-Agent' => 'Googlebot/2.1 (+http://www.google.com/bot.html)'])
        ->postJson('/api/v1/ads/track/impression', ['token' => $token]);

    $res->assertOk(); // لا تسريب لحالة الاحتساب
    expect((int) AdCounter::where('ad_placement_id', $p->id)->value('impressions'))->toBe(0);
});

it('redirects a valid click to the stored landing url and counts it', function (): void {
    $p = trackablePlacement();
    $token = AdBeaconToken::issue($p->id, (int) $p->ad_zone_id, AdBucket::current());

    $res = $this->get('/api/v1/ads/click/'.$token);

    $res->assertRedirect('https://advertiser.test/go');
    expect($res->headers->get('Cache-Control'))->toContain('no-store')
        ->and((int) AdCounter::where('ad_placement_id', $p->id)->value('clicks'))->toBe(1);
});

it('rejects an invalid click token without redirecting', function (): void {
    $res = $this->get('/api/v1/ads/click/bogus.signature');

    $res->assertStatus(422);
});

it('caps ad tracking by a per-IP ceiling regardless of X-Client-Id rotation (V1)', function (): void {
    config(['advertising.tracking.per_ip_rate_limit' => 2]);

    $p = trackablePlacement();
    $token = AdBeaconToken::issue($p->id, (int) $p->ad_zone_id, AdBucket::current());

    $this->withHeaders(['X-Client-Id' => 'rot-1'])->postJson('/api/v1/ads/track/impression', ['token' => $token])->assertOk();
    $this->withHeaders(['X-Client-Id' => 'rot-2'])->postJson('/api/v1/ads/track/impression', ['token' => $token])->assertOk();
    // تجاوز سقف الـ IP (2/دقيقة) رغم تدوير X-Client-Id ⇒ 429.
    $this->withHeaders(['X-Client-Id' => 'rot-3'])->postJson('/api/v1/ads/track/impression', ['token' => $token])->assertStatus(429);
});

it('caps clicks by IP under strict_click_dedup despite X-Client-Id rotation (V1)', function (): void {
    config(['advertising.tracking.strict_click_dedup' => true]);

    $p = trackablePlacement();
    $token = AdBeaconToken::issue($p->id, (int) $p->ad_zone_id, AdBucket::current());

    $this->withHeaders(['X-Client-Id' => 'rot-1'])->get('/api/v1/ads/click/'.$token)->assertRedirect('https://advertiser.test/go');
    $this->withHeaders(['X-Client-Id' => 'rot-2'])->get('/api/v1/ads/click/'.$token)->assertRedirect('https://advertiser.test/go');

    // ارتكاز على IP ⇒ نقرة واحدة محتسبة رغم اختلاف X-Client-Id.
    expect((int) AdCounter::where('ad_placement_id', $p->id)->value('clicks'))->toBe(1);
});

it('counts clicks per client when strict IP dedup is disabled (default, V1)', function (): void {
    $p = trackablePlacement();
    $token = AdBeaconToken::issue($p->id, (int) $p->ad_zone_id, AdBucket::current());

    $this->withHeaders(['X-Client-Id' => 'rot-1'])->get('/api/v1/ads/click/'.$token)->assertRedirect('https://advertiser.test/go');
    $this->withHeaders(['X-Client-Id' => 'rot-2'])->get('/api/v1/ads/click/'.$token)->assertRedirect('https://advertiser.test/go');

    // الافتراضي (strict_click_dedup=false): ارتكاز على الفاعل ⇒ نقرتان لعميلين مختلفين.
    expect((int) AdCounter::where('ad_placement_id', $p->id)->value('clicks'))->toBe(2);
});

it('records a click via the HTML click beacon for a valid token (V2)', function (): void {
    $p = trackablePlacement();
    $token = AdBeaconToken::issue($p->id, (int) $p->ad_zone_id, AdBucket::current());

    $res = $this->postJson('/api/v1/ads/track/click', ['token' => $token]);

    $res->assertOk();
    expect($res->json('data.accepted'))->toBeTrue()
        ->and($res->headers->get('Cache-Control'))->toContain('no-store')
        ->and((int) AdCounter::where('ad_placement_id', $p->id)->value('clicks'))->toBe(1);
});

it('rejects an invalid HTML click beacon token without counting (V2)', function (): void {
    $res = $this->postJson('/api/v1/ads/track/click', ['token' => 'bogus.signature']);

    $res->assertStatus(422);
    expect((int) AdCounter::sum('clicks'))->toBe(0);
});

it('does not count a click beacon from a bot user-agent but still accepts (V2)', function (): void {
    $p = trackablePlacement();
    $token = AdBeaconToken::issue($p->id, (int) $p->ad_zone_id, AdBucket::current());

    $res = $this->withHeaders(['User-Agent' => 'Googlebot/2.1 (+http://www.google.com/bot.html)'])
        ->postJson('/api/v1/ads/track/click', ['token' => $token]);

    $res->assertOk();
    expect((int) AdCounter::where('ad_placement_id', $p->id)->value('clicks'))->toBe(0);
});
