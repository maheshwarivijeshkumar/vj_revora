<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Contracts;

use App\Domain\Integrations\Exceptions\ProviderAuthException;
use App\Domain\Integrations\Exceptions\ProviderRequestException;
use App\Domain\Messaging\DataObjects\ConnectedMailbox;

/**
 * A mailbox connected by signing in with the provider, not a password (§11).
 */
interface OAuthMailbox extends Mailbox
{
    /** Whether the app credentials are set, so a connection can complete. */
    public function isConfigured(): bool;

    public function authorizationUrl(string $state, string $redirectUri): string;

    /**
     * @throws ProviderAuthException|ProviderRequestException
     */
    public function connect(string $code, string $redirectUri): ConnectedMailbox;
}
