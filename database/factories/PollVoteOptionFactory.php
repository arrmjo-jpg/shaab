<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PollOption;
use App\Models\PollVote;
use App\Models\PollVoteOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PollVoteOption> */
class PollVoteOptionFactory extends Factory
{
    protected $model = PollVoteOption::class;

    public function definition(): array
    {
        return [
            'poll_vote_id' => PollVote::factory(),
            'poll_option_id' => PollOption::factory(),
        ];
    }
}
