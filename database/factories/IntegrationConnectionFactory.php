<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Integrations\Enums\ConnectionStatus;
use App\Domain\Integrations\Enums\LeadAdsPlatform;
use App\Models\IntegrationConnection;
use App\Models\LeadSource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IntegrationConnection>
 */
final class IntegrationConnectionFactory extends Factory
{
    protected $model = IntegrationConnection::class;

    /**
     * A Facebook Page by default.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider' => LeadAdsPlatform::Meta,
            'account_id' => (string) fake()->unique()->numberBetween(100000, 999999),
            'account_name' => fake()->company(),
            'status' => ConnectionStatus::Connected,
            'access_token' => 'page-token-'.fake()->lexify('??????'),
            'lead_source_id' => fn (array $attributes): int => LeadSource::factory()->connected('meta')->create([
                'tenant_id' => $attributes['tenant_id'],
            ])->id,
        ];
    }

    public function linkedIn(): static
    {
        return $this->state(fn (): array => [
            'provider' => LeadAdsPlatform::LinkedIn,
            'account_id' => 'urn:li:sponsoredAccount:'.fake()->unique()->numberBetween(100000, 999999),
            'token_expires_at' => now()->addDays(50),
            'lead_source_id' => fn (array $attributes): int => LeadSource::factory()->connected('linkedin')->create([
                'tenant_id' => $attributes['tenant_id'],
            ])->id,
        ]);
    }

    public function needsReconnect(): static
    {
        return $this->state(fn (): array => ['status' => ConnectionStatus::NeedsReconnect]);
    }
}
