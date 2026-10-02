<?php

declare(strict_types=1);

namespace App\Domain\Billing\Enums;

/** Subscription lifecycle per §8. */
enum SubscriptionStatus: string
{
    case Trial = 'trial';
    case Active = 'active';
    case PastDue = 'past_due';
    case Paused = 'paused';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    case GracePeriod = 'grace_period';

    /**
     * Whether this status still grants access to paid features.
     *
     * Past-due and grace-period deliberately return true: cutting a customer
     * off the instant a card fails loses accounts that a dunning email would
     * have recovered. Expiry is what actually revokes access.
     */
    public function grantsAccess(): bool
    {
        return match ($this) {
            self::Trial, self::Active, self::PastDue, self::GracePeriod => true,
            self::Paused, self::Cancelled, self::Expired => false,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Trial => 'Trial',
            self::Active => 'Active',
            self::PastDue => 'Past Due',
            self::Paused => 'Paused',
            self::Cancelled => 'Cancelled',
            self::Expired => 'Expired',
            self::GracePeriod => 'Grace Period',
        };
    }
}
