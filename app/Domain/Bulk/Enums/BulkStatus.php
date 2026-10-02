<?php

declare(strict_types=1);

namespace App\Domain\Bulk\Enums;

/**
 * Where a bulk operation has got to (§113).
 */
enum BulkStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Completed = 'completed';
    /** Finished, but some records were skipped. */
    case PartiallyCompleted = 'partial';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Queued',
            self::Running => 'In progress',
            self::Completed => 'Done',
            self::PartiallyCompleted => 'Done with skipped records',
            self::Failed => 'Failed',
        };
    }

    public function isFinished(): bool
    {
        return $this === self::Completed
            || $this === self::PartiallyCompleted
            || $this === self::Failed;
    }
}
