<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\VideoCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VideoCategory>
 */
class VideoCategoryFactory extends Factory
{
    protected $model = VideoCategory::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'parent_id' => null,
            'locale' => 'ar',
            'name' => $name,
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
