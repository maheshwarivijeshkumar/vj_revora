<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Tenancy\Enums\TenantStatus;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tenant>
 */
final class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 999999),
            'status' => TenantStatus::Active,
            'provisioned_at' => now(),
        ];
    }

    public function provisioning(): static
    {
        return $this->state(fn () => [
            'status' => TenantStatus::Provisioning,
            'provisioned_at' => null,
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['status' => TenantStatus::Suspended]);
    }
}
