<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\BroadcastCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BroadcastCategory>
 */
class BroadcastCategoryFactory extends Factory
{
    protected $model = BroadcastCategory::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
