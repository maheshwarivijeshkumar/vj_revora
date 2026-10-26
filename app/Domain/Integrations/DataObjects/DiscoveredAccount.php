<?php

declare(strict_types=1);

namespace App\Domain\Integrations\DataObjects;

use Carbon\CarbonInterface;

/**
 * An asset the user authorized during OAuth, ready to become a connection.
 */
final readonly class DiscoveredAccount
{
    /**
     * @param  array<string, mixed>  $settings
     */
    public function __construct(
        public string $accountId,
        public string $name,
        public string $accessToken,
        public ?CarbonInterface $tokenExpiresAt = null,
        public array $settings = [],
    ) {}
}
