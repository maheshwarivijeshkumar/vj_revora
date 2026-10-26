<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Exceptions;

use RuntimeException;

/**
 * A lead cannot be messaged on a channel: no address, or they opted out.
 */
final class CannotMessageLead extends RuntimeException
{
    public static function optedOut(string $channel): self
    {
        return new self("This lead opted out of {$channel} messages.");
    }

    public static function noAddress(string $channel): self
    {
        return new self($channel === 'email' ? 'This lead has no email address.' : 'This lead has no phone number.');
    }

    public static function noSender(string $channel): self
    {
        return new self($channel === 'email'
            ? 'Connect an email account in Settings → Email first.'
            : 'Connect WhatsApp in Settings → WhatsApp first.');
    }

    public static function windowClosed(): self
    {
        return new self('WhatsApp only allows free-form messages within 24 hours of the lead\'s last message. Send the approved template instead.');
    }
}
