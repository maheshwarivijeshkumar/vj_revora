<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A single metered event (§9).
 *
 * `idempotency_key` is uniquely indexed per tenant so a retried job cannot
 * double-count usage.
 */
final class UsageRecord extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    /** @return MorphTo<Model, $this> */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['occurred_at' => 'datetime'];
    }
}
