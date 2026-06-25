<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BroadcastKind;
use App\Enums\BroadcastSourceType;
use App\Enums\BroadcastStatus;
use App\Models\Broadcast;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Broadcast>
 *
 * المصدر الافتراضي مضيف موثوق (يوتيوب لايف ضمن allow-list الافتراضية) — يبقى صالحاً
 * حتى لو أُعيد التحقّق منه. slug + uuid يُولَّدان تلقائياً (Sluggable + booted).
 */
class BroadcastFactory extends Factory
{
    protected $model = Broadcast::class;

    public function definition(): array
    {
        return [
            'title' => fake()->unique()->sentence(4),
            'excerpt' => fake()->optional()->sentence(),
            'description' => fake()->optional()->paragraph(),
            'kind' => BroadcastKind::Live->value,
            'source_type' => BroadcastSourceType::YoutubeLive->value,
            'source_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'status' => BroadcastStatus::Draft->value,
            'category_id' => null,
            'viewer_count' => 0,
            'sort_order' => 0,
            'is_featured' => false,
            'is_public' => false,
        ];
    }

    public function scheduled(): static
    {
        return $this->state(fn (): array => [
            'status' => BroadcastStatus::Scheduled->value,
            'scheduled_at' => now()->addHour(),
        ]);
    }

    public function live(): static
    {
        return $this->state(fn (): array => [
            'status' => BroadcastStatus::Live->value,
            'started_at' => now()->subMinutes(5),
        ]);
    }

    public function featured(): static
    {
        return $this->state(fn (): array => ['is_featured' => true]);
    }

    public function publicListed(): static
    {
        return $this->state(fn (): array => ['is_public' => true]);
    }

    public function tv(): static
    {
        return $this->state(fn (): array => ['kind' => BroadcastKind::Tv->value]);
    }

    public function radio(): static
    {
        return $this->state(fn (): array => ['kind' => BroadcastKind::Radio->value]);
    }
}
