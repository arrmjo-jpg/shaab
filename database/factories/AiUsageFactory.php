<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AiUsage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiUsage>
 */
class AiUsageFactory extends Factory
{
    protected $model = AiUsage::class;

    public function definition(): array
    {
        $tokens = fake()->numberBetween(50, 800);

        return [
            'user_id' => User::factory(),
            'provider' => fake()->randomElement(['openai', 'gemini']),
            'action' => fake()->randomElement(['headlines', 'excerpt', 'rewrite', 'tags', 'seo', 'analyze']),
            'source' => 'ai',
            'tokens' => $tokens,
            'estimated_cost' => round($tokens / 1000 * 0.005, 6),
            'created_at' => now(),
        ];
    }
}
