<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Contracts;

use App\Domain\Integrations\Exceptions\ProviderAuthException;
use App\Domain\Integrations\Exceptions\ProviderRequestException;
use App\Domain\Messaging\DataObjects\IncomingEmail;
use App\Domain\Messaging\DataObjects\OutgoingEmail;
use App\Models\EmailAccount;
use Carbon\CarbonInterface;

/**
 * Sends from and reads the inbox of one kind of mailbox (§25).
 */
interface Mailbox
{
    /**
     * @throws ProviderAuthException when the login or token no longer works.
     * @throws ProviderRequestException for anything a retry may fix.
     */
    public function send(EmailAccount $account, OutgoingEmail $email): void;

    /**
     * Inbox messages received since the given moment.
     *
     * @return iterable<IncomingEmail>
     *
     * @throws ProviderAuthException|ProviderRequestException
     */
    public function fetchInbox(EmailAccount $account, CarbonInterface $since): iterable;
}
