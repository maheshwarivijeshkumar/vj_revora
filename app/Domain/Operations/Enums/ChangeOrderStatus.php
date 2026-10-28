<?php

declare(strict_types=1);

namespace App\Domain\Operations\Enums;

enum ChangeOrderStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Draft => 'neutral',
            self::Approved => 'success',
            self::Rejected => 'danger',
        };
    }
}
