<?php

declare(strict_types=1);

namespace App\Domain\Operations\Enums;

enum ClientStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Inactive => 'Inactive',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Inactive => 'neutral',
        };
    }
}
