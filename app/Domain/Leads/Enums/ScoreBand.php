<?php

declare(strict_types=1);

namespace App\Domain\Leads\Enums;

/**
 * Score bands (§119).
 *
 * The default thresholds live here; a workspace may override them, which is
 * exactly why the resolved band is persisted on the lead rather than derived
 * on read. Retuning thresholds must not rewrite the history of every lead
 * already scored.
 */
enum ScoreBand: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case VeryHigh = 'very_high';

    /** Default lower bounds, inclusive. */
    public const DEFAULT_THRESHOLDS = [
        'medium' => 40,
        'high' => 70,
        'very_high' => 90,
    ];

    /**
     * @param  array{medium?: int, high?: int, very_high?: int}  $thresholds
     */
    public static function forScore(int $score, array $thresholds = []): self
    {
        $t = [...self::DEFAULT_THRESHOLDS, ...$thresholds];

        return match (true) {
            $score >= $t['very_high'] => self::VeryHigh,
            $score >= $t['high'] => self::High,
            $score >= $t['medium'] => self::Medium,
            default => self::Low,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Low',
            self::Medium => 'Medium',
            self::High => 'High',
            self::VeryHigh => 'Very High',
        };
    }

    /** Whether this band clears the bar for automatic qualification. */
    public function isHot(): bool
    {
        return in_array($this, [self::High, self::VeryHigh], true);
    }
}
