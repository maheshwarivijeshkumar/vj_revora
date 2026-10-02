<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use App\Domain\Webhooks\Enums\DeliveryStatus;
use App\Domain\Webhooks\Enums\WebhookEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * One attempt to deliver one event to one endpoint (§49).
 *
 * @property int $id
 * @property string $uuid
 * @property int $tenant_id
 * @property int $webhook_endpoint_id
 * @property WebhookEvent $event
 * @property array<string, mixed> $payload
 * @property DeliveryStatus $status
 * @property int $attempt
 * @property int|null $response_status
 * @property string|null $response_body
 * @property string|null $error
 * @property int|null $duration_ms
 * @property Carbon|null $delivered_at
 * @property int|null $replay_of_id
 * @property Carbon|null $created_at
 */
final class WebhookDelivery extends Model
{
    use BelongsToTenant;

    /**
     * How much of a subscriber's response body is worth keeping.
     *
     * Enough to recognise a stack trace or an error message; not enough for an
     * endpoint returning a full HTML page to fill the table.
     */
    public const RESPONSE_EXCERPT = 2000;

    protected $guarded = [];

    /**
     * Bound by uuid, so an internal id never appears in a URL and a sequential
     * id cannot be used to guess at another workspace's records.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected static function booted(): void
    {
        self::creating(function (self $delivery): void {
            $delivery->uuid ??= (string) Str::uuid7();
        });
    }

    /** @return BelongsTo<WebhookEndpoint, $this> */
    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(WebhookEndpoint::class, 'webhook_endpoint_id');
    }

    /**
     * The delivery this one is a hand-triggered re-send of, if any.
     *
     * @return BelongsTo<self, $this>
     */
    public function replayOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replay_of_id');
    }

    public function wasSuccessful(): bool
    {
        return $this->status === DeliveryStatus::Delivered;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event' => WebhookEvent::class,
            'status' => DeliveryStatus::class,
            'payload' => 'array',
            'delivered_at' => 'datetime',
        ];
    }
}
