<?php

declare(strict_types=1);

use App\Models\Broadcast;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

function latestActivityFor(string $subjectType, int $subjectId, string $event): ?Activity
{
    return Activity::query()
        ->where('subject_type', $subjectType)
        ->where('subject_id', $subjectId)
        ->where('event', $event)
        ->latest('id')
        ->first();
}

it('captures changed non-secret attributes (new + old) in properties on update', function (): void {
    $user = User::factory()->create(['name' => 'Old Name']);
    $user->update(['name' => 'New Name']);

    $activity = latestActivityFor(User::class, $user->id, 'updated');

    expect($activity)->not->toBeNull();

    $properties = $activity->properties->toArray();
    expect($properties)->toHaveKeys(['attributes', 'old']);
    expect($properties['attributes']['name'])->toBe('New Name');
    expect($properties['old']['name'])->toBe('Old Name');
});

it('records an attribute snapshot in properties on create', function (): void {
    $broadcast = Broadcast::factory()->create(['title' => 'Launch']);

    $activity = latestActivityFor(Broadcast::class, $broadcast->id, 'created');

    expect($activity)->not->toBeNull();
    expect($activity->properties->toArray()['attributes']['title'])->toBe('Launch');
});

it('never writes secret attributes into properties even when they change', function (): void {
    $user = User::factory()->create(['name' => 'Before']);
    $user->update(['name' => 'After', 'password' => 'brand-new-secret']);

    $activity = latestActivityFor(User::class, $user->id, 'updated');

    expect($activity)->not->toBeNull();

    $attributes = $activity->properties->toArray()['attributes'] ?? [];
    expect($attributes)->toHaveKey('name');
    expect($attributes)->not->toHaveKey('password');
    expect($attributes)->not->toHaveKey('remember_token');
});
