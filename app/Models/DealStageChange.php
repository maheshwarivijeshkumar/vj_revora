<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A deal moving between stages. Append-only.
 *
 * Without this there is no way to answer how long deals sit in each stage,
 * which is the first thing anyone asks of a pipeline report.
 *
 * @property int $deal_id
 * @property int|null $from_stage_id
 * @property int $to_stage_id
 * @property int|null $seconds_in_previous_stage
 * @property CarbonInterface|null $changed_at
 */
final class DealStageChange extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    /** @return BelongsTo<Deal, $this> */
    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    /** @return BelongsTo<PipelineStage, $this> */
    public function fromStage(): BelongsTo
    {
        return $this->belongsTo(PipelineStage::class, 'from_stage_id');
    }

    /** @return BelongsTo<PipelineStage, $this> */
    public function toStage(): BelongsTo
    {
        return $this->belongsTo(PipelineStage::class, 'to_stage_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['changed_at' => 'datetime'];
    }
}
