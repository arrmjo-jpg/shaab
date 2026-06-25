<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AdCreative;
use App\Models\AdPlacement;
use App\Models\AdZone;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AdPlacement> */
class AdPlacementFactory extends Factory
{
    protected $model = AdPlacement::class;

    public function definition(): array
    {
        return [
            'ad_creative_id' => AdCreative::factory(),
            'ad_zone_id' => AdZone::factory(),
            'weight' => null,
            'device_targets' => null,
            'is_active' => true,
        ];
    }
}
