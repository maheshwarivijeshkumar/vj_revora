<?php

declare(strict_types=1);

namespace App\Domain\Operations\Enums;

enum EstimateStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Accepted = 'accepted';
    case Declined = 'declined';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Sent => 'Sent',
            self::Accepted => 'Accepted',
            self::Declined => 'Declined',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Draft => 'neutral',
            self::Sent => 'info',
            self::Accepted => 'success',
            self::Declined => 'danger',
        };
    }
}
