<?php

declare(strict_types=1);

namespace App\Domain\Activity\Enums;

/**
 * How much a task matters (§21).
 *
 * Three levels, not five. A five-point scale collapses to "urgent" and
 * "everything else" within a week, and the middle three stop meaning anything.
 */
enum TaskPriority: string
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Low',
            self::Normal => 'Normal',
            self::High => 'High',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Low => 'neutral',
            self::Normal => 'info',
            self::High => 'danger',
        };
    }

    /** Sort weight, so high comes first in a list. */
    public function weight(): int
    {
        return match ($this) {
            self::High => 0,
            self::Normal => 1,
            self::Low => 2,
        };
    }

    /**
     * @return list<array{value: string, label: string, tone: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case): array => [
                'value' => $case->value,
                'label' => $case->label(),
                'tone' => $case->tone(),
            ],
            self::cases(),
        );
    }
}
