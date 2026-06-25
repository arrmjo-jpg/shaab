<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\VideoStatus;
use App\Enums\VideoVisibility;
use App\Models\User;
use App\Models\VideoPlaylist;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<VideoPlaylist>
 */
class VideoPlaylistFactory extends Factory
{
    protected $model = VideoPlaylist::class;

    public function definition(): array
    {
        $title = fake()->unique()->sentence(3);

        return [
            'uuid' => (string) Str::uuid(),
            'author_id' => User::factory(),
            'locale' => 'ar',
            'title' => $title,
            'description' => fake()->optional()->paragraph(),
            'cover_media_id' => null,
            'status' => VideoStatus::Draft->value,
            'visibility' => VideoVisibility::Public->value,
            'is_featured' => false,
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
}
