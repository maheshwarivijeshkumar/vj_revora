<?php

declare(strict_types=1);

namespace App\Domain\Operations\Enums;

enum ProjectStatus: string
{
    case Planning = 'planning';
    case Active = 'active';
    case OnHold = 'on_hold';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Planning => 'Planning',
            self::Active => 'Active',
            self::OnHold => 'On hold',
            self::Completed => 'Completed',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Planning => 'info',
            self::Active => 'success',
            self::OnHold => 'warning',
            self::Completed => 'neutral',
        };
    }
}
