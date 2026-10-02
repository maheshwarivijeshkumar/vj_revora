<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Company;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
final class CompanyFactory extends Factory
{
    protected $model = Company::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->company(),
            'website' => 'https://'.fake()->unique()->domainName(),
            'industry' => fake()->randomElement(['SaaS', 'Real estate', 'Education', 'Healthcare']),
            'country' => 'AE',
        ];
    }
}
