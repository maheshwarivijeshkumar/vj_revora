<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A written note against any entity (§21).
 *
 * Soft deleted and editable, unlike a lead event: a note is somebody's words,
 * and people correct their own words.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $subject_type
 * @property int $subject_id
 * @property string $body
 * @property bool $is_pinned
 * @property int|null $user_id
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
final class Note extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $guarded = [];

    /** @return MorphTo<Model, $this> */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Pinned first, then newest.
     *
     * The pinned note is the one somebody picking this account up needs to read
     * before anything else, so it does not get pushed down by activity.
     *
     * @param  Builder<self>  $query
     */
    public function scopeDisplayOrder(Builder $query): void
    {
        $query->orderByDesc('is_pinned')->orderByDesc('id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_pinned' => 'boolean'];
    }
}
