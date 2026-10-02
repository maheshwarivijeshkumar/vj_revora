<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single feature grant on a plan.
 *
 * `value` is a JSON column so one field expresses a boolean capability, a
 * numeric limit or a structured grant without a schema change per feature
 * type. It is deliberately *not* cast to 'array': the column legitimately
 * holds scalars (true, 5000) as well as objects, and an array cast both
 * misdescribes the type and breaks on scalar payloads.
 *
 * @property int $id
 * @property int $plan_id
 * @property string $feature_key
 * @property bool $is_unlimited
 */
final class PlanFeature extends Model
{
    protected $guarded = [];

    /** @return BelongsTo<Plan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * The decoded payload: bool, int, string, array or null.
     *
     * Reads through the cast rather than the raw attribute so encoding stays
     * symmetric — the 'json' cast json_encodes on write and decodes on read,
     * which is what keeps scalars like `true` valid JSON text in the column.
     */
    public function value(): mixed
    {
        return $this->getAttribute('value');
    }

    /**
     * Whether the plan grants this feature at all.
     *
     * An unlimited grant is enabled by definition. Otherwise anything falsy —
     * null, false, 0 — reads as ungranted.
     */
    public function isEnabled(): bool
    {
        if ($this->is_unlimited) {
            return true;
        }

        $value = $this->value();

        return $value !== null && $value !== false && $value !== 0;
    }

    /**
     * The numeric cap, or null when the feature is unlimited or not a quantity.
     *
     * Booleans are excluded explicitly: `true` means "granted", never "1".
     */
    public function limit(): ?int
    {
        if ($this->is_unlimited) {
            return null;
        }

        $value = $this->value();

        if (is_bool($value) || ! is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // 'json', not 'array': the column legitimately holds scalars
            // (true, 5000) as well as objects. The 'array' cast forces every
            // read to an array and misdescribes the type; 'json' round-trips
            // scalars correctly, and writing without a cast at all produces
            // `1` for true, which MySQL rejects as invalid JSON text.
            'value' => 'json',
            'is_unlimited' => 'boolean',
        ];
    }
}
