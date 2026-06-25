<?php

declare(strict_types=1);

use App\Enums\BroadcastKind;
use App\Enums\BroadcastSourceType;
use App\Enums\BroadcastStatus;
use App\Models\Broadcast;
use App\Models\BroadcastCategory;
use App\Support\Cache\BroadcastCacheTags;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('migrates and creates a broadcast with auto uuid, slug, and enum casts', function (): void {
    $b = Broadcast::factory()->create(['title' => 'بثّ تجريبي']);

    expect($b->uuid)->not->toBeEmpty();
    expect($b->slug)->not->toBeEmpty();
    expect($b->status)->toBeInstanceOf(BroadcastStatus::class);
    expect($b->kind)->toBeInstanceOf(BroadcastKind::class);
    expect($b->source_type)->toBeInstanceOf(BroadcastSourceType::class);
    expect($b->is_public)->toBeBool();
    expect($b->is_featured)->toBeBool();
});

it('belongs to a flat category and exposes creator/updater + soft deletes', function (): void {
    $cat = BroadcastCategory::factory()->create();
    $b = Broadcast::factory()->for($cat, 'category')->create();

    expect($b->category->id)->toBe($cat->id);
    expect($cat->broadcasts()->count())->toBe(1);

    $b->delete();
    expect(Broadcast::find($b->id))->toBeNull();
    expect(Broadcast::withTrashed()->find($b->id)->trashed())->toBeTrue();
});

it('generates a unique arabic-aware slug for duplicate titles', function (): void {
    $a = Broadcast::factory()->create(['title' => 'مباراة اليوم']);
    $b = Broadcast::factory()->create(['title' => 'مباراة اليوم']);

    expect($a->slug)->not->toBeEmpty();
    expect($b->slug)->not->toBeEmpty();
    expect($a->slug)->not->toBe($b->slug);
});

it('casts meta to array and persists health snapshot + viewer snapshot fields', function (): void {
    $b = Broadcast::factory()->create([
        'meta' => ['k' => 'v'],
        'viewer_count' => 123,
        'last_health_status' => 'ok',
        'last_health_message' => 'manifest reachable',
    ]);

    $fresh = $b->fresh();
    expect($fresh->meta)->toBe(['k' => 'v']);
    expect($fresh->viewer_count)->toBe(123);
    expect($fresh->last_health_status)->toBe('ok');
});

it('creates active flat broadcast categories with a slug', function (): void {
    $c = BroadcastCategory::factory()->create();

    expect($c->slug)->not->toBeEmpty();
    expect($c->is_active)->toBeTrue();
});

it('builds granular cache tags with kind + old-slug/kind/category invalidation', function (): void {
    $b = Broadcast::factory()->tv()->create();

    expect(BroadcastCacheTags::feedTags('tv'))->toBe([BroadcastCacheTags::ALL, 'broadcasts:feed:tv']);

    $tags = BroadcastCacheTags::invalidationTags($b, oldKind: 'live', oldSlug: 'old-slug', categorySlug: 'sports');
    expect($tags)->toContain('broadcasts:feed:tv')      // new kind
        ->toContain('broadcasts:feed:live')             // old kind
        ->toContain('broadcasts:detail:old-slug')       // old slug
        ->toContain('broadcasts:category:sports')
        ->toContain(BroadcastCacheTags::SITEMAP);
});
