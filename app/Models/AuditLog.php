<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * The audit trail (§54).
 *
 * Central with a nullable tenant_id so platform and tenant actions share one
 * queryable trail. Not tenant-scoped at the model level — reading it is
 * gated by policy instead, since platform staff must be able to read across
 * tenants during support. A tenant-facing reader therefore has to apply the
 * workspace filter itself; AuditLogController does.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property string|null $actor_type
 * @property int|null $actor_id
 * @property string $action
 * @property string|null $entity_type
 * @property int|string|null $entity_id
 * @property array<string, mixed>|null $before
 * @property array<string, mixed>|null $after
 * @property string|null $ip
 * @property string|null $user_agent
 * @property Carbon|null $created_at
 */
final class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    /** @return MorphTo<Model, $this> */
    public function actor(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return MorphTo<Model, $this> */
    public function entity(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
