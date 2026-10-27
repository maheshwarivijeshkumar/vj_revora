<?php

declare(strict_types=1);

namespace App\Domain\Activity\Enums;

/**
 * Where a task has got to (§21).
 */
enum TaskStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Done = 'done';
    /** Decided against, rather than forgotten. */
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'To do',
            self::InProgress => 'In progress',
            self::Done => 'Done',
            self::Cancelled => 'Cancelled',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Open => 'info',
            self::InProgress => 'warning',
            self::Done => 'success',
            self::Cancelled => 'neutral',
        };
    }

    /**
     * Whether the task still wants doing.
     *
     * Cancelled counts as finished: it was decided, and a decision is not
     * outstanding work.
     */
    public function isOpen(): bool
    {
        return $this === self::Open || $this === self::InProgress;
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
