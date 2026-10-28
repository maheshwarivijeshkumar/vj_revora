<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AdCampaign;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdCampaign>
 */
final class AdCampaignFactory extends Factory
{
    protected $model = AdCampaign::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'platform' => 'facebook',
            'external_id' => (string) fake()->unique()->numberBetween(1000, 999999),
            'name' => fake()->catchPhrase(),
            'status' => 'ACTIVE',
            'last_seen_at' => now(),
        ];
    }
}
