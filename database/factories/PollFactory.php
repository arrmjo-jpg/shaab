<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PollAudienceMode;
use App\Enums\PollResultVisibility;
use App\Models\Poll;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Poll> */
class PollFactory extends Factory
{
    protected $model = Poll::class;

    public function definition(): array
    {
        return [
            'question' => rtrim($this->faker->sentence(), '.').'?',
            'allow_multiple' => false,
            'is_active' => true,
            'starts_at' => null,
            'ends_at' => null,
            'audience_mode' => PollAudienceMode::Everyone->value,
            'result_visibility' => PollResultVisibility::AfterVote->value,
            'created_by' => null,
            'updated_by' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }

    public function multiple(): static
    {
        return $this->state(fn (): array => ['allow_multiple' => true]);
    }
}
