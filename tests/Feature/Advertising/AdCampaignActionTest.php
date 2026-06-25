<?php

declare(strict_types=1);

use App\Actions\Admin\Advertising\CreateAdCampaignAction;
use App\Actions\Admin\Advertising\DeleteAdCampaignAction;
use App\Actions\Admin\Advertising\ForceDeleteAdCampaignAction;
use App\Actions\Admin\Advertising\ListAdCampaignsAction;
use App\Actions\Admin\Advertising\RestoreAdCampaignAction;
use App\Actions\Admin\Advertising\UpdateAdCampaignAction;
use App\Enums\AdCampaignStatus;
use App\Models\AdCampaign;
use App\Models\AdCreative;
use App\Models\AdPlacement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::flush());

it('forces draft status and stamps actor on create', function (): void {
    $actor = User::factory()->create();

    $res = (new CreateAdCampaignAction)->handle([
        'name' => 'Summer Push',
        'status' => 'active', // يجب تجاهلها — الإنشاء = مسودّة دائماً.
        'priority' => 5,
    ], $actor);

    expect($res->getStatusCode())->toBe(201);

    $campaign = AdCampaign::where('name', 'Summer Push')->first();

    expect($campaign)->not->toBeNull()
        ->and($campaign->status)->toBe(AdCampaignStatus::Draft)
        ->and($campaign->created_by)->toBe($actor->id)
        ->and($campaign->updated_by)->toBe($actor->id)
        ->and($campaign->uuid)->not->toBeEmpty();
});

it('updates a campaign and stamps the editor', function (): void {
    $actor = User::factory()->create();
    $campaign = AdCampaign::factory()->create(['name' => 'Old', 'priority' => 0]);

    $res = (new UpdateAdCampaignAction)->handle($campaign, ['name' => 'New', 'priority' => 9], $actor);

    expect($res->getStatusCode())->toBe(200)
        ->and($campaign->fresh()->name)->toBe('New')
        ->and($campaign->fresh()->priority)->toBe(9)
        ->and($campaign->fresh()->updated_by)->toBe($actor->id);
});

it('paginates campaigns with the standard meta envelope', function (): void {
    AdCampaign::factory()->count(3)->create();

    $res = (new ListAdCampaignsAction)->handle();
    $payload = $res->getData(true);

    expect($res->getStatusCode())->toBe(200)
        ->and($payload['data'])->toHaveCount(3)
        ->and($payload['meta']['pagination']['total'])->toBe(3)
        ->and($payload['meta']['pagination']['current_page'])->toBe(1);
});

it('soft-deletes then restores a campaign', function (): void {
    $campaign = AdCampaign::factory()->create();

    (new DeleteAdCampaignAction)->handle($campaign);

    expect(AdCampaign::whereKey($campaign->id)->exists())->toBeFalse()
        ->and(AdCampaign::withTrashed()->whereKey($campaign->id)->exists())->toBeTrue();

    (new RestoreAdCampaignAction)->handle($campaign);

    expect(AdCampaign::whereKey($campaign->id)->exists())->toBeTrue();
});

it('force-deletes a campaign and cascades to creatives and placements', function (): void {
    $campaign = AdCampaign::factory()->create();
    $creative = AdCreative::factory()->create(['ad_campaign_id' => $campaign->id]);
    $placement = AdPlacement::factory()->create(['ad_creative_id' => $creative->id]);

    $res = (new ForceDeleteAdCampaignAction)->handle($campaign);

    expect($res->getStatusCode())->toBe(200)
        ->and(AdCampaign::withTrashed()->whereKey($campaign->id)->exists())->toBeFalse()
        ->and(AdCreative::withTrashed()->whereKey($creative->id)->exists())->toBeFalse()
        ->and(AdPlacement::whereKey($placement->id)->exists())->toBeFalse();
});

it('writes an activity-log entry when a campaign is created', function (): void {
    $actor = User::factory()->create();

    (new CreateAdCampaignAction)->handle(['name' => 'Audited'], $actor);

    expect(Activity::where('log_name', 'ad_campaign')->where('event', 'created')->exists())->toBeTrue();
});
