<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One column on a board (§22).
 *
 * @property string $name
 * @property string $key
 * @property int $probability
 * @property int $sort_order
 * @property bool $is_won
 * @property bool $is_lost
 *                         `required_fields` is workspace-authored JSON, so it is typed loosely on
 *                         purpose and filtered at read time rather than trusted.
 * @property array<mixed>|null $required_fields
 */
final class PipelineStage extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    /** @return BelongsTo<Pipeline, $this> */
    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }

    /** @return HasMany<Deal, $this> */
    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }

    public function isTerminal(): bool
    {
        return $this->is_won || $this->is_lost;
    }

    /**
     * Fields a deal must have filled before it may enter this stage.
     *
     * @return list<string>
     */
    public function requiredFields(): array
    {
        return array_values(array_filter(
            $this->required_fields ?? [],
            static fn (mixed $field): bool => is_string($field) && $field !== '',
        ));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_won' => 'boolean',
            'is_lost' => 'boolean',
            'required_fields' => 'array',
        ];
    }
}
