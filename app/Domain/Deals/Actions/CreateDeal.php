<?php

declare(strict_types=1);

namespace App\Domain\Deals\Actions;

use App\Domain\Deals\Enums\DealStatus;
use App\Models\Deal;
use App\Models\DealStageChange;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use Illuminate\Support\Facades\DB;

/**
 * Opens a deal on a board (§22).
 *
 * Deals land at the top of their stage rather than the bottom: a newly opened
 * opportunity is the one most likely to need attention, and burying it under
 * older cards is the opposite of useful.
 */
final class CreateDeal
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(array $attributes, ?Pipeline $pipeline = null, ?PipelineStage $stage = null): Deal
    {
        $pipeline ??= $this->defaultPipeline();
        $stage ??= $pipeline->firstStage();

        if (! $stage instanceof PipelineStage) {
            throw new \RuntimeException('That pipeline has no stages, so a deal cannot be opened on it.');
        }

        return DB::transaction(function () use ($attributes, $pipeline, $stage): Deal {
            // Everything already in the stage shifts down by one.
            Deal::query()
                ->where('pipeline_stage_id', $stage->id)
                ->increment('position');

            $deal = Deal::create([
                ...$attributes,
                'pipeline_id' => $pipeline->id,
                'pipeline_stage_id' => $stage->id,
                'probability' => $attributes['probability'] ?? $stage->probability,
                'status' => $attributes['status'] ?? DealStatus::Open,
                'position' => 0,
            ]);

            // The opening move, so stage-duration reporting starts from a real
            // entry rather than inferring one from created_at.
            DealStageChange::create([
                'deal_id' => $deal->id,
                'from_stage_id' => null,
                'to_stage_id' => $stage->id,
                'changed_at' => now(),
            ]);

            return $deal;
        });
    }

    private function defaultPipeline(): Pipeline
    {
        $pipeline = Pipeline::query()
            ->where('entity_type', 'deal')
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->first();

        if (! $pipeline instanceof Pipeline) {
            throw new \RuntimeException('This workspace has no deal pipeline yet.');
        }

        return $pipeline;
    }
}
