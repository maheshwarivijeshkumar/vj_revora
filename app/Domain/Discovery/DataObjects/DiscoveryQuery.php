<?php

declare(strict_types=1);

namespace App\Domain\Discovery\DataObjects;

/**
 * A location search for one kind of local business.
 */
final readonly class DiscoveryQuery
{
    public function __construct(
        public string $city,
        public ?string $state,
        public string $country,
        public string $category,
        public string $source,
    ) {}

    public function place(): string
    {
        return implode(', ', array_filter([
            $this->city,
            $this->state,
            $this->country,
        ], fn (?string $part): bool => $part !== null && trim($part) !== ''));
    }
}
