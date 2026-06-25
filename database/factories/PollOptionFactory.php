<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Poll;
use App\Models\PollOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PollOption> */
class PollOptionFactory extends Factory
{
    protected $model = PollOption::class;

    public function definition(): array
    {
        return [
            'poll_id' => Poll::factory(),
            'label' => $this->faker->words(2, true),
            'sort_order' => 0,
            'votes_count' => 0,
        ];
    }
}
