<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Leads\Enums\LeadSourceType;
use App\Models\LeadSource;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LeadSource>
 */
final class LeadSourceFactory extends Factory
{
    protected $model = LeadSource::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // words() is typed string|array in the Faker stubs; asking for the
        // string form directly avoids the ambiguity.
        $name = fake()->unique()->word().' '.fake()->word();

        return [
            'key' => Str::slug($name),
            'name' => Str::title($name),
            'type' => LeadSourceType::Form,
            'is_active' => true,
        ];
    }

    public function connected(string $provider = 'meta'): static
    {
        return $this->state(fn (): array => [
            'type' => LeadSourceType::Connected,
            'provider' => $provider,
        ]);
    }

    public function imported(): static
    {
        return $this->state(fn (): array => ['type' => LeadSourceType::Import]);
    }
}
