<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Activity\Enums\ActivityType;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Something a person did, against any entity (§21).
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $subject_type
 * @property int $subject_id
 * @property ActivityType $type
 * @property string $summary
 * @property string|null $body
 * @property int|null $user_id
 * @property CarbonInterface $occurred_at
 * @property int|null $duration_minutes
 * @property array<string, mixed>|null $metadata
 * @property CarbonInterface|null $created_at
 */
final class Activity extends Model
{
    use BelongsToTenant;

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
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ActivityType::class,
            'occurred_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
