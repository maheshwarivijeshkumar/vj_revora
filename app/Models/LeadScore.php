<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Leads\Enums\ScoreBand;
use App\Domain\Leads\Enums\ScoreMethod;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One scoring run, kept for history (§19).
 *
 * `reasons` is the user-facing explanation. Model chain-of-thought is never
 * stored here, or anywhere (§20).
 *
 * @property ScoreBand $band
 * @property ScoreMethod $method
 * @property list<array{label: string, points: int}> $reasons
 */
final class LeadScore extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    /** @return BelongsTo<Lead, $this> */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'band' => ScoreBand::class,
            'method' => ScoreMethod::class,
            'reasons' => 'array',
            'computed_at' => 'datetime',
        ];
    }
}
