<?php

declare(strict_types=1);

namespace App\Domain\Opportunities\Enums;

/**
 * Where the workspace is with one tender or job post.
 */
enum OpportunityStatus: string
{
    case New = 'new';
    case Shortlisted = 'shortlisted';
    /** A proposal or pitch has been drafted, not yet sent. */
    case Drafted = 'drafted';
    /** Submitted on Upwork, sent to the company, or bid on the tender. */
    case Applied = 'applied';
    case Won = 'won';
    case Lost = 'lost';
    case Dismissed = 'dismissed';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Shortlisted => 'Shortlisted',
            self::Drafted => 'Draft ready',
            self::Applied => 'Applied',
            self::Won => 'Won',
            self::Lost => 'Lost',
            self::Dismissed => 'Dismissed',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::New => 'new',
            self::Shortlisted => 'info',
            self::Drafted => 'proposal',
            self::Applied => 'contacted',
            self::Won => 'success',
            self::Lost => 'lost',
            self::Dismissed => 'neutral',
        };
    }

    /** Still something to act on. */
    public function isOpen(): bool
    {
        return in_array($this, [self::New, self::Shortlisted, self::Drafted], true);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $s): array => ['value' => $s->value, 'label' => $s->label()], self::cases());
    }
}
