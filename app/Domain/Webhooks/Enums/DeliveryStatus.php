<?php

declare(strict_types=1);

namespace App\Domain\Webhooks\Enums;

/**
 * Where a single delivery attempt ended up (§49).
 */
enum DeliveryStatus: string
{
    case Pending = 'pending';
    case Delivered = 'delivered';
    /** Failed, but another attempt is scheduled. */
    case Retrying = 'retrying';
    /** Out of attempts. Still replayable by hand. */
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Queued',
            self::Delivered => 'Delivered',
            self::Retrying => 'Retrying',
            self::Failed => 'Failed',
        };
    }

    public function isSettled(): bool
    {
        return $this === self::Delivered || $this === self::Failed;
    }
}
