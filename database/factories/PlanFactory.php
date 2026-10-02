<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
final class PlanFactory extends Factory
{
    protected $model = Plan::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(2),
            'name' => fake()->word(),
            'interval' => 'monthly',
            'price' => fake()->randomFloat(2, 0, 500),
            'currency' => 'USD',
            'trial_days' => 14,
            'is_public' => true,
            'is_active' => true,
        ];
    }
}
