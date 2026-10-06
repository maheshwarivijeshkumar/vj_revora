<?php

declare(strict_types=1);

namespace App\Domain\Bulk\Enums;

/**
 * What a bulk action does (§113).
 */
enum BulkAction: string
{
    case Assign = 'assign';
    case ChangeStatus = 'change_status';
    case AddTag = 'add_tag';
    case RemoveTag = 'remove_tag';
    case Verify = 'verify';
    case Delete = 'delete';

    public function label(): string
    {
        return match ($this) {
            self::Assign => 'Assign owner',
            self::ChangeStatus => 'Change status',
            self::AddTag => 'Add tag',
            self::RemoveTag => 'Remove tag',
            self::Verify => 'Check details',
            self::Delete => 'Delete',
        };
    }

    /**
     * Whether the UI must confirm, naming the exact count (§113).
     */
    public function isDestructive(): bool
    {
        return $this === self::Delete;
    }

    /**
     * The permission this action needs.
     *
     * Per action rather than one blanket "bulk" permission: someone who may
     * reassign leads is not thereby allowed to delete them.
     */
    public function permission(string $entity): string
    {
        return match ($this) {
            self::Assign => "{$entity}.assign",
            self::Delete => "{$entity}.delete",
            // Verification reads the record and writes only its own columns,
            // so viewing is enough: it tells you nothing you could not already
            // see, and gating it behind edit would stop the people who most
            // need it from triaging a list.
            self::Verify => "{$entity}.view",
            default => "{$entity}.update",
        };
    }
}
