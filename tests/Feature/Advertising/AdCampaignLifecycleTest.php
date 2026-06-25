<?php

declare(strict_types=1);

use App\Enums\AdCampaignStatus;
use App\Models\AdCampaign;
use App\Support\Advertising\AdCampaignLifecycle;

it('defines the manual transition matrix exactly', function (): void {
    expect(AdCampaignLifecycle::manualTargets(AdCampaignStatus::Draft))
        ->toBe([AdCampaignStatus::Scheduled, AdCampaignStatus::Archived]);

    // draft → active is NOT directly publishable (must flow via scheduled).
    expect(AdCampaignLifecycle::canTransitionManually(AdCampaignStatus::Draft, AdCampaignStatus::Active))->toBeFalse()
        ->and(AdCampaignLifecycle::canTransitionManually(AdCampaignStatus::Scheduled, AdCampaignStatus::Active))->toBeTrue()
        ->and(AdCampaignLifecycle::canTransitionManually(AdCampaignStatus::Completed, AdCampaignStatus::Paused))->toBeTrue()
        ->and(AdCampaignLifecycle::canTransitionManually(AdCampaignStatus::Archived, AdCampaignStatus::Paused))->toBeTrue()
        ->and(AdCampaignLifecycle::canTransitionManually(AdCampaignStatus::Archived, AdCampaignStatus::Draft))->toBeTrue()
        ->and(AdCampaignLifecycle::canTransitionManually(AdCampaignStatus::Completed, AdCampaignStatus::Active))->toBeFalse()
        ->and(AdCampaignLifecycle::canTransitionManually(AdCampaignStatus::Active, AdCampaignStatus::Draft))->toBeFalse();
});

it('computes scheduler auto transitions and never touches paused/draft', function (): void {
    $mk = fn (string $status, $start, $end): AdCampaign => new AdCampaign([
        'status' => $status, 'starts_at' => $start, 'ends_at' => $end,
    ]);

    expect(AdCampaignLifecycle::autoTransitionFor($mk('scheduled', now()->subHour(), now()->addHour())))
        ->toBe(AdCampaignStatus::Active)
        ->and(AdCampaignLifecycle::autoTransitionFor($mk('scheduled', now()->subDays(2), now()->subDay())))
        ->toBe(AdCampaignStatus::Completed)
        ->and(AdCampaignLifecycle::autoTransitionFor($mk('scheduled', now()->addHour(), now()->addDay())))
        ->toBeNull()
        ->and(AdCampaignLifecycle::autoTransitionFor($mk('active', now()->subDay(), now()->subHour())))
        ->toBe(AdCampaignStatus::Completed)
        ->and(AdCampaignLifecycle::autoTransitionFor($mk('active', now()->subHour(), now()->addHour())))
        ->toBeNull()
        ->and(AdCampaignLifecycle::autoTransitionFor($mk('paused', now()->subDay(), now()->subHour())))
        ->toBeNull()
        ->and(AdCampaignLifecycle::autoTransitionFor($mk('draft', now()->subDay(), now()->addDay())))
        ->toBeNull();
});
