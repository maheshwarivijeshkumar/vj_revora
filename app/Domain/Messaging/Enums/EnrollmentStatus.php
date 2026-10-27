<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Enums;

/**
 * Where a lead is in a follow-up sequence.
 */
enum EnrollmentStatus: string
{
    case Active = 'active';
    /** The lead answered; the sequence stopped and the reply was scored. */
    case Replied = 'replied';
    /** Every step went out and the reply window closed: scored 0/10. */
    case NoReply = 'no_reply';
    /** Stopped by a person, or because the lead opted out. */
    case Stopped = 'stopped';
    /** Could not send (no mailbox, bad address). */
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'In progress',
            self::Replied => 'Replied',
            self::NoReply => 'No reply',
            self::Stopped => 'Stopped',
            self::Failed => 'Failed',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Active => 'info',
            self::Replied => 'success',
            self::NoReply => 'neutral',
            self::Stopped => 'warning',
            self::Failed => 'danger',
        };
    }
}
