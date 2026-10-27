<?php

declare(strict_types=1);

namespace App\Domain\Activity\Enums;

/**
 * What kind of thing happened (§21).
 *
 * Only what a person does. System events — captured, scored, verified — live in
 * `lead_events`, because mixing "we ran a rule" into the same list as "I rang
 * them on Tuesday" makes the list unreadable for both purposes.
 */
enum ActivityType: string
{
    case Call = 'call';
    case Meeting = 'meeting';
    case Email = 'email';
    case Message = 'message';
    case Note = 'note';
    case Task = 'task';
    case StatusChange = 'status_change';
    case Document = 'document';

    public function label(): string
    {
        return match ($this) {
            self::Call => 'Call',
            self::Meeting => 'Meeting',
            self::Email => 'Email',
            self::Message => 'Message',
            self::Note => 'Note',
            self::Task => 'Task',
            self::StatusChange => 'Status change',
            self::Document => 'Document',
        };
    }

    /**
     * Whether someone can log this by hand.
     *
     * Notes, tasks, documents and status changes are recorded as a side effect
     * of doing the thing, so offering them in a "log an activity" menu would
     * invite two records of one event.
     */
    public function isLoggable(): bool
    {
        return match ($this) {
            self::Call, self::Meeting, self::Email, self::Message => true,
            default => false,
        };
    }

    /** Whether a duration is worth asking for. */
    public function hasDuration(): bool
    {
        return $this === self::Call || $this === self::Meeting;
    }

    /**
     * @return list<array{value: string, label: string, has_duration: bool}>
     */
    public static function loggableOptions(): array
    {
        return array_values(array_map(
            fn (self $case): array => [
                'value' => $case->value,
                'label' => $case->label(),
                'has_duration' => $case->hasDuration(),
            ],
            array_filter(self::cases(), fn (self $case): bool => $case->isLoggable()),
        ));
    }

    /**
     * @return list<string>
     */
    public static function loggableValues(): array
    {
        return array_values(array_map(
            fn (self $case): string => $case->value,
            array_filter(self::cases(), fn (self $case): bool => $case->isLoggable()),
        ));
    }
}
