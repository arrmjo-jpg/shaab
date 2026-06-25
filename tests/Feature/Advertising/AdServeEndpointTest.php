<?php

declare(strict_types=1);

use App\Models\AdCampaign;
use App\Models\AdCreative;
use App\Models\AdPlacement;
use App\Models\AdZone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::flush());

/** مساحة بنر نشِطة + حملة قابلة للعرض + إبداع HTML نشِط مُسنَد (بلا اعتماد على وسيط). */
function servableHtmlPlacement(string $zoneKey = 'home_top'): AdPlacement
{
    $zone = AdZone::factory()->create([
        'key' => $zoneKey,
        'placement_type' => 'banner',
        'is_active' => true,
        'locale' => null,
    ]);

    $campaign = AdCampaign::factory()->create(); // نشطة + بلا نافذة ⇒ قابلة للعرض

    $creative = AdCreative::factory()->html()->create([
        'ad_campaign_id' => $campaign->id,
        'is_active' => true,
        'landing_url' => 'https://advertiser.test/landing',
    ]);

    return AdPlacement::factory()->create([
        'ad_creative_id' => $creative->id,
        'ad_zone_id' => $zone->id,
        'is_active' => true,
    ]);
}

it('serves a chosen creative with impression and click tokens', function (): void {
    servableHtmlPlacement();

    $res = $this->getJson('/api/v1/ads/serve/home_top');

    $res->assertOk();

    expect($res->json('data.ad'))->not->toBeNull()
        ->and($res->json('data.ad.type'))->toBe('html')
        ->and($res->json('data.ad.render.html'))->toContain('ad')
        ->and($res->json('data.ad.impression.token'))->not->toBeEmpty()
        ->and($res->json('data.ad.click.url'))->toContain('/api/v1/ads/click/')
        ->and($res->json('meta.expires_in'))->toBeGreaterThan(0);
});

it('marks the serve response edge-cacheable for the bucket window', function (): void {
    servableHtmlPlacement();

    $res = $this->getJson('/api/v1/ads/serve/home_top');

    expect($res->headers->get('Cache-Control'))->toContain('public')
        ->and($res->headers->get('Cache-Control'))->toContain('s-maxage')
        // V4: لا stale-while-revalidate — يمنع تقديم نسخة رمزها خارج نافذة الدلو.
        ->and($res->headers->get('Cache-Control'))->not->toContain('stale-while-revalidate');
});

it('returns a null ad for an unknown zone', function (): void {
    $res = $this->getJson('/api/v1/ads/serve/nonexistent_zone');

    $res->assertOk();
    expect($res->json('data.ad'))->toBeNull();
});

it('stops serving once the campaign window expires, even while the pool is still cached (V3)', function (): void {
    $placement = servableHtmlPlacement();

    // أوّل عرض: يبني ويُكاش البِركة، ويعيد إعلاناً.
    expect($this->getJson('/api/v1/ads/serve/home_top')->json('data.ad'))->not->toBeNull();

    // تنتهي نافذة الحملة دون إبطال البِركة (تحديث نموذج مباشر يحاكي مرور الوقت قبل إعادة البناء).
    $placement->creative->campaign->update(['ends_at' => now()->subMinute()]);

    // العرض التالي: البِركة ما زالت مُكاشة، لكن إعادة التحقّق وقت العرض تُسقط الحملة المنتهية.
    expect($this->getJson('/api/v1/ads/serve/home_top')->json('data.ad'))->toBeNull();
});
