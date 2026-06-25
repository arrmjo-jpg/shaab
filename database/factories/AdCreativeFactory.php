<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AdCreativeType;
use App\Models\AdCampaign;
use App\Models\AdCreative;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AdCreative> */
class AdCreativeFactory extends Factory
{
    protected $model = AdCreative::class;

    public function definition(): array
    {
        return [
            'ad_campaign_id' => AdCampaign::factory(),
            'type' => AdCreativeType::Image->value,
            'title' => $this->faker->sentence(3),
            'alt_text' => $this->faker->sentence(4),
            'landing_url' => $this->faker->url(),
            'html_code' => null,
            'media_asset_id' => null,
            'weight' => 1,
            'is_active' => true,
        ];
    }

    public function html(): static
    {
        return $this->state(fn (): array => [
            'type' => AdCreativeType::Html->value,
            'html_code' => '<div>ad</div>',
            'landing_url' => null,
        ]);
    }
}
