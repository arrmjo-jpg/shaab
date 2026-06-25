<?php

declare(strict_types=1);

use App\Models\Poll;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('is open only when active and within the window', function (): void {
    expect(Poll::factory()->make(['is_active' => true, 'starts_at' => null, 'ends_at' => null])->isOpenForVoting())->toBeTrue();
    expect(Poll::factory()->make(['is_active' => false])->isOpenForVoting())->toBeFalse();
    expect(Poll::factory()->make(['is_active' => true, 'starts_at' => now()->addDay()])->isOpenForVoting())->toBeFalse();
    expect(Poll::factory()->make(['is_active' => true, 'ends_at' => now()->subDay()])->isOpenForVoting())->toBeFalse();
});

it('derives a display state', function (): void {
    expect(Poll::factory()->make(['is_active' => false])->state())->toBe('inactive');
    expect(Poll::factory()->make(['is_active' => true, 'starts_at' => now()->addDay()])->state())->toBe('scheduled');
    expect(Poll::factory()->make(['is_active' => true, 'ends_at' => now()->subDay()])->state())->toBe('closed');
    expect(Poll::factory()->make(['is_active' => true, 'starts_at' => null, 'ends_at' => null])->state())->toBe('open');
});

it('votable scope returns only open polls', function (): void {
    Poll::factory()->create(['is_active' => true]);                                  // open
    Poll::factory()->inactive()->create();                                           // inactive
    Poll::factory()->create(['is_active' => true, 'ends_at' => now()->subDay()]);     // closed
    Poll::factory()->create(['is_active' => true, 'starts_at' => now()->addDay()]);   // scheduled

    expect(Poll::query()->votable()->count())->toBe(1);
});
