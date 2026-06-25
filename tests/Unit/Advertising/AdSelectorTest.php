<?php

declare(strict_types=1);

use App\Enums\AdSelectorStrategy;
use App\Support\Advertising\AdBucket;
use App\Support\Advertising\AdSelectorFactory;
use App\Support\Advertising\Selectors\EvenSelector;
use App\Support\Advertising\Selectors\RoundRobinSelector;
use App\Support\Advertising\Selectors\WeightedSelector;

/** @return array<int,array<string,mixed>> */
function adCands(int ...$weightsByCreativeId): array
{
    $out = [];
    $cid = 1;
    foreach ($weightsByCreativeId as $w) {
        $out[] = ['placement_id' => $cid, 'creative_id' => $cid * 10, 'type' => 'image', 'weight' => $w];
        $cid++;
    }

    return $out;
}

it('builds a deterministic, segmented bucket seed', function (): void {
    expect(AdBucket::seed('home_top', 'ar', 'mobile', 10293))->toBe('home_top:ar:mobile:bucket_10293');
    expect(AdBucket::current(30))->toBeInt();
});

it('weighted selection is deterministic for a given seed and returns null on empty', function (): void {
    $s = new WeightedSelector;
    $seed = AdBucket::seed('z', 'ar', 'desktop', 5);

    expect($s->select(adCands(1, 1), 5, $seed))->toBe($s->select(adCands(1, 1), 5, $seed));
    expect($s->select([], 5, $seed))->toBeNull();
});

it('weighted selection favors heavier weights across buckets', function (): void {
    $s = new WeightedSelector;
    $cands = adCands(1, 9); // creative 10 (w1) vs creative 20 (w9)
    $counts = [10 => 0, 20 => 0];

    for ($b = 0; $b < 300; $b++) {
        $pick = $s->select($cands, $b, AdBucket::seed('z', 'ar', 'desktop', $b));
        $counts[$pick['creative_id']]++;
    }

    expect($counts[20])->toBeGreaterThan($counts[10]);
});

it('round robin cycles sequentially across buckets', function (): void {
    $s = new RoundRobinSelector;
    $cands = adCands(1, 1, 1); // creatives 10,20,30

    expect($s->select($cands, 0, 's')['creative_id'])->toBe(10);
    expect($s->select($cands, 1, 's')['creative_id'])->toBe(20);
    expect($s->select($cands, 2, 's')['creative_id'])->toBe(30);
    expect($s->select($cands, 3, 's')['creative_id'])->toBe(10); // wraps
});

it('even selection is deterministic by seed', function (): void {
    $s = new EvenSelector;
    $cands = adCands(1, 1, 1);

    expect($s->select($cands, 5, 'seedA'))->toBe($s->select($cands, 5, 'seedA'));
    expect($s->select([], 5, 'seedA'))->toBeNull();
});

it('factory resolves strategies and falls back to weighted', function (): void {
    expect(AdSelectorFactory::make('weighted'))->toBeInstanceOf(WeightedSelector::class);
    expect(AdSelectorFactory::make('round_robin'))->toBeInstanceOf(RoundRobinSelector::class);
    expect(AdSelectorFactory::make('even'))->toBeInstanceOf(EvenSelector::class);
    expect(AdSelectorFactory::make('bogus'))->toBeInstanceOf(WeightedSelector::class);
    expect(AdSelectorFactory::make(AdSelectorStrategy::RoundRobin))->toBeInstanceOf(RoundRobinSelector::class);
});
