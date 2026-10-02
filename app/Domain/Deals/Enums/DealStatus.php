<?php

declare(strict_types=1);

namespace App\Domain\Deals\Enums;

/** Deal outcome (§22). Stage position is separate: this is the verdict. */
enum DealStatus: string
{
    case Open = 'open';
    case Won = 'won';
    case Lost = 'lost';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Won => 'Won',
            self::Lost => 'Lost',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Open => 'new',
            self::Won => 'qualified',
            self::Lost => 'lost',
        };
    }

    public function isClosed(): bool
    {
        return $this !== self::Open;
    }
}
