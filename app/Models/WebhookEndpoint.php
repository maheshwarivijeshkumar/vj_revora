<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use App\Domain\Webhooks\Enums\WebhookEvent;
use Database\Factories\WebhookEndpointFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A subscriber's HTTPS endpoint (§49).
 *
 * @property int $id
 * @property string $uuid
 * @property int $tenant_id
 * @property string $description
 * @property string $url
 * @property string $secret
 * @property list<string> $events
 * @property bool $is_active
 * @property int $consecutive_failures
 * @property Carbon|null $disabled_at
 * @property string|null $disabled_reason
 * @property Carbon|null $last_delivered_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class WebhookEndpoint extends Model
{
    /** @use HasFactory<WebhookEndpointFactory> */
    use BelongsToTenant, HasFactory, SoftDeletes;

    /**
     * How many consecutive failures retire an endpoint.
     *
     * Deliveries keep being logged after that; what stops is the outbound
     * traffic, because hammering a host that has failed twenty times running
     * is how a workspace gets its own IP blocked (§49).
     */
    public const FAILURE_LIMIT = 20;

    protected $guarded = [];

    /**
     * Bound by uuid, so an internal id never appears in a URL and a sequential
     * id cannot be used to guess at another workspace's records.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected $hidden = ['secret'];

    protected static function booted(): void
    {
        self::creating(function (self $endpoint): void {
            $endpoint->uuid ??= (string) Str::uuid7();
            $endpoint->secret ??= self::generateSecret();
        });
    }

    /**
     * A signing secret the subscriber copies into their own verification.
     *
     * Prefixed so it is recognisable in a support conversation and greppable
     * if it is ever pasted somewhere it should not be.
     */
    public static function generateSecret(): string
    {
        return 'whsec_'.Str::random(48);
    }

    /** @return HasMany<WebhookDelivery, $this> */
    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Endpoints that should receive this event right now.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeListeningFor(Builder $query, WebhookEvent $event): Builder
    {
        return $query
            ->where('is_active', true)
            ->whereNull('disabled_at')
            // whereJsonContains rather than LIKE, so `lead.created` cannot be
            // matched by a stored `lead.created_at` or similar.
            ->whereJsonContains('events', $event->value);
    }

    public function isSubscribedTo(WebhookEvent $event): bool
    {
        return in_array($event->value, $this->events, true);
    }

    public function isDeliverable(): bool
    {
        return $this->is_active && $this->disabled_at === null;
    }

    /**
     * Records a successful delivery, clearing the failure streak.
     */
    public function recordSuccess(): void
    {
        $this->forceFill([
            'consecutive_failures' => 0,
            'last_delivered_at' => now(),
        ])->save();
    }

    /**
     * Records a failed delivery, retiring the endpoint once the streak is long
     * enough that it is clearly not coming back.
     */
    public function recordFailure(): void
    {
        $failures = $this->consecutive_failures + 1;

        $attributes = ['consecutive_failures' => $failures];

        if ($failures >= self::FAILURE_LIMIT) {
            $attributes['disabled_at'] = now();
            $attributes['disabled_reason'] = sprintf(
                'Disabled automatically after %d consecutive failed deliveries.',
                $failures,
            );
        }

        $this->forceFill($attributes)->save();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'events' => 'array',
            'is_active' => 'boolean',
            'disabled_at' => 'datetime',
            'last_delivered_at' => 'datetime',
        ];
    }
}
