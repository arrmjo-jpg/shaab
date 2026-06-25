<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AdCampaignStatus;
use App\Enums\AdPacingMode;
use App\Models\AdCampaign;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AdCampaign> */
class AdCampaignFactory extends Factory
{
    protected $model = AdCampaign::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'advertiser_name' => $this->faker->company(),
            'status' => AdCampaignStatus::Active->value,
            'priority' => 0,
            'weight' => 1,
            'starts_at' => null,
            'ends_at' => null,
            'budget_total' => null,
            'budget_spent' => 0,
            'pacing_mode' => AdPacingMode::None->value,
            'targeting' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (): array => ['status' => AdCampaignStatus::Draft->value]);
    }
}
