<?php

declare(strict_types=1);

namespace App\Domain\Messaging\DataObjects;

/**
 * A mailbox the user authorized through OAuth, ready to store.
 */
final readonly class ConnectedMailbox
{
    /**
     * @param  array<string, mixed>  $credentials
     */
    public function __construct(
        public string $email,
        public ?string $name,
        public array $credentials,
    ) {}
}
