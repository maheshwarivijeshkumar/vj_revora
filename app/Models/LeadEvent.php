<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Something that happened to a lead (§86).
 *
 * Append-only: the timeline is evidence, so entries are never edited.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $lead_id
 * @property string $type
 * @property string|null $provider
 * @property string|null $provider_event_id
 * @property array<string, mixed>|null $payload
 * @property int|null $user_id
 * @property Carbon $occurred_at
 * @property Carbon|null $created_at
 */
final class LeadEvent extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    /** Well-known event types. Providers may add their own. */
    public const CAPTURED = 'lead.captured';

    public const SCORED = 'lead.scored';

    public const VERIFIED = 'lead.verified';

    public const ASSIGNED = 'lead.assigned';

    public const STATUS_CHANGED = 'lead.status_changed';

    public const MERGED = 'lead.merged';

    public const DUPLICATE_MATCHED = 'lead.duplicate_matched';

    /** @return BelongsTo<Lead, $this> */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
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
            'payload' => 'array',
            'occurred_at' => 'datetime',
        ];
    }
}
