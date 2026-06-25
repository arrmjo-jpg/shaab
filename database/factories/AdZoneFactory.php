<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AdPlacementType;
use App\Enums\AdSelectorStrategy;
use App\Models\AdZone;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AdZone> */
class AdZoneFactory extends Factory
{
    protected $model = AdZone::class;

    public function definition(): array
    {
        return [
            'key' => 'zone_'.$this->faker->unique()->numberBetween(1, 9_999_999),
            'name' => $this->faker->words(2, true),
            'description' => null,
            'placement_type' => AdPlacementType::Banner->value,
            'selector_strategy' => AdSelectorStrategy::Weighted->value,
            'width' => 728,
            'height' => 90,
            'locale' => null,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
