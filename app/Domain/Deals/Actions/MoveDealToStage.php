<?php

declare(strict_types=1);

namespace App\Domain\Deals\Actions;

use App\Domain\Deals\Enums\DealStatus;
use App\Domain\Deals\Events\DealStageChanged;
use App\Domain\Deals\Exceptions\StageTransitionException;
use App\Models\Deal;
use App\Models\DealStageChange;
use App\Models\PipelineStage;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Moves a deal between stages, and reorders it within one (§22).
 *
 * This is what the Kanban board calls on every drop, so it has to be exact
 * about ordering: a card that visibly lands in third place and then reloads
 * into fifth destroys trust in the board faster than any other bug.
 */
final class MoveDealToStage
{
    /**
     * @param  int|null  $position  Zero-based index within the target stage.
     *                              Null appends to the end.
     *
     * @throws StageTransitionException when the stage's own rules refuse the move.
     */
    public function handle(
        Deal $deal,
        PipelineStage $stage,
        ?int $position = null,
        ?int $changedBy = null,
    ): Deal {
        if ($stage->pipeline_id !== $deal->pipeline_id) {
            throw StageTransitionException::wrongPipeline();
        }

        $this->guardRequiredFields($deal, $stage);

        return DB::transaction(function () use ($deal, $stage, $position, $changedBy): Deal {
            $fromStage = $deal->pipeline_stage_id;
            $isStageChange = $fromStage !== $stage->id;

            // Measured from the last move, or from creation for the first one,
            // so stage-duration reporting has no gap at the start.
            $secondsInPrevious = $isStageChange
                ? $this->secondsSinceEnteringCurrentStage($deal)
                : null;

            $deal->pipeline_stage_id = $stage->id;

            if ($isStageChange) {
                // Probability follows the stage on entry. A rep who has since
                // adjusted it for this specific deal loses that on a move,
                // which is correct: the new stage is fresh information.
                $deal->probability = $stage->probability;
                $this->applyOutcome($deal, $stage);
            }

            $deal->save();

            $this->reposition($deal, $stage, $position);

            // The column the card left has a hole in it now. Compacting it
            // keeps positions contiguous, which matters because an index is
            // reused the moment another card is dropped there.
            if ($isStageChange) {
                $this->renumber($fromStage);
            }

            if ($isStageChange) {
                DealStageChange::create([
                    'deal_id' => $deal->id,
                    'from_stage_id' => $fromStage,
                    'to_stage_id' => $stage->id,
                    'changed_by' => $changedBy,
                    'seconds_in_previous_stage' => $secondsInPrevious,
                    'changed_at' => now(),
                ]);

                DealStageChanged::dispatch($deal->refresh(), $fromStage, $stage->id);
            }

            return $deal->refresh();
        });
    }

    /**
     * A stage may demand fields be filled before a deal enters it (§22).
     *
     * Checked before anything is written, so a rejected drop leaves the deal
     * exactly where it was.
     */
    private function guardRequiredFields(Deal $deal, PipelineStage $stage): void
    {
        $missing = [];

        foreach ($stage->requiredFields() as $field) {
            $value = $deal->getAttribute($field);

            // Zero is a legitimate value for most fields but not for the one
            // that matters here: a deal worth nothing is not a proposal.
            $isEmpty = $value === null
                || $value === ''
                || ($field === 'value' && (float) $value <= 0.0);

            if ($isEmpty) {
                $missing[] = $field;
            }
        }

        if ($missing !== []) {
            throw StageTransitionException::missingRequiredFields($stage->name, $missing);
        }
    }

    /** Terminal stages close the deal; moving back out reopens it. */
    private function applyOutcome(Deal $deal, PipelineStage $stage): void
    {
        if ($stage->is_won) {
            $deal->status = DealStatus::Won;
            $deal->closed_at = now();

            return;
        }

        if ($stage->is_lost) {
            $deal->status = DealStatus::Lost;
            $deal->closed_at = now();

            return;
        }

        // Dragging a closed deal back onto the board reopens it, which is what
        // someone correcting a mistaken drop expects to happen.
        $deal->status = DealStatus::Open;
        $deal->closed_at = null;
        $deal->lost_reason = null;
    }

    /**
     * Renumbers a stage so the deal sits at the requested index.
     *
     * Integers with renumbering rather than a fractional index: a stage holds
     * tens of cards, the write is one cheap UPDATE per card, and the numbers
     * stay readable when someone inspects the table.
     */
    private function reposition(Deal $deal, PipelineStage $stage, ?int $position): void
    {
        $others = Deal::query()
            ->where('pipeline_stage_id', $stage->id)
            ->where('id', '!=', $deal->id)
            ->boardOrder()
            ->pluck('id')
            ->all();

        $target = $position === null
            ? count($others)
            : max(0, min($position, count($others)));

        array_splice($others, $target, 0, [$deal->id]);

        $this->write($others);
    }

    /** Compacts a stage so its positions run 0..n-1 with no gaps. */
    private function renumber(int $stageId): void
    {
        $this->write(
            Deal::query()
                ->where('pipeline_stage_id', $stageId)
                ->boardOrder()
                ->pluck('id')
                ->all(),
        );
    }

    /**
     * @param  iterable<mixed>  $ids  In the order they should appear.
     */
    private function write(iterable $ids): void
    {
        $index = 0;

        foreach ($ids as $id) {
            Deal::query()->whereKey($id)->update(['position' => $index]);
            $index++;
        }
    }

    /** How long the deal has sat where it currently is. */
    private function secondsSinceEnteringCurrentStage(Deal $deal): int
    {
        $lastMove = DealStageChange::query()
            ->where('deal_id', $deal->id)
            ->orderByDesc('changed_at')
            ->first();

        $since = $lastMove instanceof DealStageChange
            ? $lastMove->changed_at
            : $deal->created_at;

        if (! $since instanceof CarbonInterface) {
            return 0;
        }

        // Carbon 3 returns a float here; stage duration is only ever reported
        // in whole seconds.
        return max(0, (int) $since->diffInSeconds(now()));
    }
}
