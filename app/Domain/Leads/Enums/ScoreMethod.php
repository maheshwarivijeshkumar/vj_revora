<?php

declare(strict_types=1);

namespace App\Domain\Leads\Enums;

/** How a score was produced (§19). */
enum ScoreMethod: string
{
    case Manual = 'manual';
    case Rules = 'rules';
    case Ai = 'ai';
    case Hybrid = 'hybrid';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Set manually',
            self::Rules => 'Rule-based',
            self::Ai => 'AI',
            self::Hybrid => 'Rules and AI',
        };
    }
}
