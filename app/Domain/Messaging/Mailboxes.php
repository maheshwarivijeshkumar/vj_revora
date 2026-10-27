<?php

declare(strict_types=1);

namespace App\Domain\Messaging;

use App\Domain\Messaging\Contracts\Mailbox;
use App\Domain\Messaging\Contracts\OAuthMailbox;
use App\Domain\Messaging\Enums\MailboxProvider;
use App\Domain\Messaging\Mailboxes\GoogleMailbox;
use App\Domain\Messaging\Mailboxes\MicrosoftMailbox;
use App\Domain\Messaging\Mailboxes\SmtpImapMailbox;
use InvalidArgumentException;

/**
 * Resolves the mailbox implementation for a provider.
 */
final class Mailboxes
{
    public function for(MailboxProvider $provider): Mailbox
    {
        return app(match ($provider) {
            MailboxProvider::Smtp => SmtpImapMailbox::class,
            MailboxProvider::Google => GoogleMailbox::class,
            MailboxProvider::Microsoft => MicrosoftMailbox::class,
        });
    }

    public function oauth(MailboxProvider $provider): OAuthMailbox
    {
        $mailbox = $this->for($provider);

        if (! $mailbox instanceof OAuthMailbox) {
            throw new InvalidArgumentException("{$provider->label()} is not connected with OAuth.");
        }

        return $mailbox;
    }
}
