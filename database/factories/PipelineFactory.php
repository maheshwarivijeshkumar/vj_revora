<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Deals\Enums\PipelineEntity;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Pipeline>
 */
final class PipelineFactory extends Factory
{
    protected $model = Pipeline::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->word().' pipeline';

        return [
            'tenant_id' => Tenant::factory(),
            'name' => Str::title($name),
            'key' => Str::slug($name),
            'entity_type' => PipelineEntity::Deal,
            'is_default' => true,
        ];
    }

    /** Creates the pipeline with the standard stage set already on it. */
    public function withDefaultStages(): static
    {
        return $this->afterCreating(function (Pipeline $pipeline): void {
            foreach (Pipeline::defaultStages() as $i => $stage) {
                // forceCreate because BelongsToTenant guards tenant_id against
                // mass assignment. Stages must land on the pipeline's own
                // tenant, which is not necessarily the bound one when a
                // factory builds a workspace from scratch.
                PipelineStage::forceCreate([
                    'tenant_id' => $pipeline->tenant_id,
                    'pipeline_id' => $pipeline->id,
                    'name' => $stage['name'],
                    'key' => $stage['key'],
                    'probability' => $stage['probability'],
                    'sort_order' => $i,
                    'is_won' => $stage['is_won'] ?? false,
                    'is_lost' => $stage['is_lost'] ?? false,
                    'required_fields' => $stage['required_fields'] ?? null,
                ]);
            }
        });
    }
}
