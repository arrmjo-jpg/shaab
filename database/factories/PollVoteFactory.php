<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Poll;
use App\Models\PollVote;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PollVote> */
class PollVoteFactory extends Factory
{
    protected $model = PollVote::class;

    public function definition(): array
    {
        return [
            'poll_id' => Poll::factory(),
            'voter_hash' => hash('sha256', (string) $this->faker->unique()->uuid()),
            'created_at' => now(),
        ];
    }
}
