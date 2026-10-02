<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Enums;

enum TenantStatus: string
{
    /** Provisioning started but has not completed. Resumable — see ProvisionTenant. */
    case Provisioning = 'provisioning';
    case Active = 'active';
    case Suspended = 'suspended';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Provisioning => 'Provisioning',
            self::Active => 'Active',
            self::Suspended => 'Suspended',
            self::Cancelled => 'Cancelled',
        };
    }

    /** Whether users of this tenant may sign in and use the application. */
    public function permitsAccess(): bool
    {
        return $this === self::Active;
    }
}
