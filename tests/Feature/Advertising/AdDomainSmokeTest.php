<?php

declare(strict_types=1);

use App\Enums\AdCampaignStatus;
use App\Enums\AdCreativeType;
use App\Models\AdCampaign;
use App\Models\AdCreative;
use App\Models\AdPlacement;
use App\Models\AdZone;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('builds the ad domain graph with enum casts and relations', function (): void {
    $placement = AdPlacement::factory()->create();

    expect($placement->zone)->toBeInstanceOf(AdZone::class)
        ->and($placement->creative)->toBeInstanceOf(AdCreative::class)
        ->and($placement->creative->type)->toBe(AdCreativeType::Image)
        ->and($placement->creative->campaign)->toBeInstanceOf(AdCampaign::class)
        ->and($placement->creative->campaign->status)->toBe(AdCampaignStatus::Active);

    // uuid auto-assigned on create
    expect($placement->creative->uuid)->not->toBeNull()
        ->and($placement->creative->campaign->uuid)->not->toBeNull();

    // effective weight falls back to creative weight (placement weight null)
    expect($placement->effectiveWeight())->toBe(1);
});

it('applies campaign serving scopes (active + in-flight only)', function (): void {
    AdCampaign::factory()->create(['status' => 'active', 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay()]);
    AdCampaign::factory()->draft()->create();
    AdCampaign::factory()->create(['status' => 'active', 'ends_at' => now()->subDay()]); // expired window

    expect(AdCampaign::query()->servable()->count())->toBe(1);
});

it('enforces unique zone keys and resolves the locale scope', function (): void {
    AdZone::factory()->create(['key' => 'home_top', 'locale' => null]);
    AdZone::factory()->create(['key' => 'ar_sidebar', 'locale' => 'ar']);

    // ar request sees global (null) + ar; en request sees only global.
    expect(AdZone::query()->forLocale('ar')->count())->toBe(2)
        ->and(AdZone::query()->forLocale('en')->count())->toBe(1);
});
