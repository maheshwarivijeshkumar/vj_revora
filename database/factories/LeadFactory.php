<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Leads\Enums\LeadStatus;
use App\Domain\Leads\Enums\ScoreBand;
use App\Models\Lead;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
final class LeadFactory extends Factory
{
    protected $model = Lead::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $first = fake()->firstName();
        $last = fake()->lastName();

        return [
            // Leads are tenant-owned; without this the factory throws outside
            // a bound tenant context.
            'tenant_id' => Tenant::factory(),
            'first_name' => $first,
            'last_name' => $last,
            'email' => fake()->unique()->safeEmail(),
            'phone' => '+9715'.fake()->numerify('########'),
            'company_name' => fake()->company(),
            'status' => LeadStatus::New,
            'score' => 0,
            'score_band' => ScoreBand::Low,
            'consent' => true,
        ];
    }

    public function status(LeadStatus $status): static
    {
        return $this->state(fn (): array => ['status' => $status]);
    }

    public function scored(int $score): static
    {
        return $this->state(fn (): array => [
            'score' => $score,
            'score_band' => ScoreBand::forScore($score),
        ]);
    }

    /** A lead with no identifying details beyond a name. */
    public function anonymous(): static
    {
        return $this->state(fn (): array => ['email' => null, 'phone' => null]);
    }
}
