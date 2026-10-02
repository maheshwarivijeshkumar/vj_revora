<?php

declare(strict_types=1);

namespace App\Domain\Leads\Exceptions;

use RuntimeException;

final class UnusableLeadException extends RuntimeException
{
    public static function make(): self
    {
        return new self(
            'A lead needs at least an email address, a phone number, an external '
            .'record id or a name. Nothing identifiable was supplied.'
        );
    }
}
