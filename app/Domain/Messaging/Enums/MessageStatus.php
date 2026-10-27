<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Enums;

/**
 * Where a message is in its delivery (§15).
 */
enum MessageStatus: string
{
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Read = 'read';
    case Failed = 'failed';
    case Received = 'received';

    public function label(): string
    {
        return match ($this) {
            self::Sent => 'Sent',
            self::Delivered => 'Delivered',
            self::Read => 'Read',
            self::Failed => 'Failed',
            self::Received => 'Received',
        };
    }

    /**
     * Statuses only move forward: a late "delivered" webhook must not
     * overwrite a "read" that arrived first.
     */
    public function rank(): int
    {
        return match ($this) {
            self::Failed => 0,
            self::Sent => 1,
            self::Delivered => 2,
            self::Read => 3,
            self::Received => 3,
        };
    }
}
