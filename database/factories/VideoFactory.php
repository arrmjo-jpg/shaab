<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\VideoStatus;
use App\Enums\VideoVisibility;
use App\Models\User;
use App\Models\Video;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Video>
 */
class VideoFactory extends Factory
{
    protected $model = Video::class;

    public function definition(): array
    {
        $title = fake()->unique()->sentence(4);

        return [
            'uuid' => (string) Str::uuid(),
            'author_id' => User::factory(),
            'media_asset_id' => null,
            'video_category_id' => null,
            'source_type' => 'youtube',
            'status' => VideoStatus::Draft->value,
            'visibility' => VideoVisibility::Public->value,
            'is_featured' => false,
            'locale' => 'ar',
            'title' => $title,
            'description' => fake()->optional()->paragraph(),
            'excerpt' => fake()->optional()->sentence(),
            'duration_seconds' => fake()->numberBetween(30, 7200),
            'views_count' => 0,
            'sort_order' => 0,
            'published_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => [
            'status' => VideoStatus::Published->value,
            'published_at' => now()->subDay(),
        ]);
    }

    public function featured(): static
    {
        return $this->state(fn (): array => ['is_featured' => true]);
    }
}
