<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Enums;

/**
 * Whether a connected account can still be synced (§57, §59).
 */
enum ConnectionStatus: string
{
    case Connected = 'connected';
    /** The token expired or was revoked. Only the user can fix this. */
    case NeedsReconnect = 'needs_reconnect';
    /** The last sync failed for a reason a retry may fix. */
    case Failing = 'failing';

    public function label(): string
    {
        return match ($this) {
            self::Connected => 'Connected',
            self::NeedsReconnect => 'Reconnect needed',
            self::Failing => 'Sync failing',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Connected => 'success',
            self::NeedsReconnect => 'danger',
            self::Failing => 'warning',
        };
    }

    /** Whether the scheduler should keep trying this connection. */
    public function isSyncable(): bool
    {
        return $this !== self::NeedsReconnect;
    }
}
