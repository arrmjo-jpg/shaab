<?php

declare(strict_types=1);

use App\Actions\Admin\Advertising\ChangeAdCampaignStatusAction;
use App\Actions\Admin\Advertising\TickAdCampaignsAction;
use App\Enums\AdCampaignStatus;
use App\Models\AdCampaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::flush());

it('performs a valid manual transition and rejects an invalid one', function (): void {
    $user = User::factory()->create();

    $c = AdCampaign::factory()->draft()->create();
    $ok = (new ChangeAdCampaignStatusAction)->handle($c, AdCampaignStatus::Scheduled, $user);
    expect($ok->getStatusCode())->toBe(200)
        ->and($c->fresh()->status)->toBe(AdCampaignStatus::Scheduled);

    $draft = AdCampaign::factory()->draft()->create();
    $bad = (new ChangeAdCampaignStatusAction)->handle($draft, AdCampaignStatus::Active, $user);
    expect($bad->getStatusCode())->toBe(422)
        ->and($draft->fresh()->status)->toBe(AdCampaignStatus::Draft);
});

it('blocks manual activation when the window has expired', function (): void {
    $user = User::factory()->create();
    $c = AdCampaign::factory()->create(['status' => 'scheduled', 'ends_at' => now()->subDay()]);

    $res = (new ChangeAdCampaignStatusAction)->handle($c, AdCampaignStatus::Active, $user);
    expect($res->getStatusCode())->toBe(422)
        ->and($c->fresh()->status)->toBe(AdCampaignStatus::Scheduled);
});

it('allows completed→paused and archived→paused (restore / extend)', function (): void {
    $user = User::factory()->create();

    $completed = AdCampaign::factory()->create(['status' => 'completed']);
    expect((new ChangeAdCampaignStatusAction)->handle($completed, AdCampaignStatus::Paused, $user)->getStatusCode())->toBe(200)
        ->and($completed->fresh()->status)->toBe(AdCampaignStatus::Paused);

    $archived = AdCampaign::factory()->create(['status' => 'archived']);
    expect((new ChangeAdCampaignStatusAction)->handle($archived, AdCampaignStatus::Paused, $user)->getStatusCode())->toBe(200)
        ->and($archived->fresh()->status)->toBe(AdCampaignStatus::Paused);
});

it('scheduler activates due, completes ended/missed, and leaves paused & draft untouched', function (): void {
    AdCampaign::factory()->create(['status' => 'scheduled', 'starts_at' => now()->subHour(), 'ends_at' => now()->addHour()]);   // → active
    AdCampaign::factory()->create(['status' => 'scheduled', 'starts_at' => now()->subDays(2), 'ends_at' => now()->subDay()]);   // → completed (missed)
    AdCampaign::factory()->create(['status' => 'active', 'ends_at' => now()->subHour()]);                                       // → completed
    AdCampaign::factory()->create(['status' => 'paused', 'ends_at' => now()->subHour()]);                                       // untouched
    AdCampaign::factory()->draft()->create(['starts_at' => now()->subDay()]);                                                   // untouched

    expect((new TickAdCampaignsAction)->handle())->toBe(3);

    expect(AdCampaign::where('status', 'active')->count())->toBe(1)
        ->and(AdCampaign::where('status', 'completed')->count())->toBe(2)
        ->and(AdCampaign::where('status', 'paused')->count())->toBe(1)
        ->and(AdCampaign::where('status', 'draft')->count())->toBe(1);
});
