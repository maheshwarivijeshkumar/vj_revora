<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An audit record of one lead being merged into another (§18).
 *
 * `diff` holds exactly which fields were copied from the duplicate, so a merge
 * can be explained afterwards and unpicked by hand if it was wrong.
 */
final class LeadMerge extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    /** @return BelongsTo<Lead, $this> */
    public function master(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'master_lead_id');
    }

    /** @return BelongsTo<Lead, $this> */
    public function duplicate(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'merged_lead_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'diff' => 'array',
            'merged_at' => 'datetime',
        ];
    }
}
