<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Billing\Enums\SubscriptionStatus;
use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A tenant's subscription. Central record — deliberately not tenant-scoped,
 * because billing is administered from the platform side.
 *
 * @property int $id
 * @property int $tenant_id
 * @property SubscriptionStatus $status
 */
final class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use HasFactory;

    protected $guarded = [];

    protected static function booted(): void
    {
        self::creating(function (self $s): void {
            $s->uuid ??= (string) Str::uuid7();
        });
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsTo<Plan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * The plan a scheduled downgrade will move to at period end. Entitlements
     * stay on the current plan until then.
     *
     * @return BelongsTo<Plan, $this>
     */
    public function scheduledPlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'scheduled_plan_id');
    }

    /** Whether the subscription currently grants access to paid features. */
    public function isUsable(): bool
    {
        return $this->status->grantsAccess();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'trial_ends_at' => 'datetime',
            'cancels_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'grace_ends_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
