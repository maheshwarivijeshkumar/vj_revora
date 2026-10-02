<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Deals\Enums\DealStatus;
use App\Models\Deal;
use App\Models\Pipeline;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Deal>
 */
final class DealFactory extends Factory
{
    protected $model = Deal::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'title' => fake()->company().' renewal',
            'value' => fake()->randomFloat(2, 1000, 90000),
            'currency' => 'USD',
            'pipeline_id' => Pipeline::factory(),
            'status' => DealStatus::Open,
            'position' => 0,
        ];
    }
}
