<?php

declare(strict_types=1);

namespace App\Domain\Discovery\DataObjects;

/**
 * One business returned by a discovery source, before it is a lead.
 */
final readonly class DiscoveredBusiness
{
    public function __construct(
        public string $provider,
        public string $sourceName,
        public string $externalId,
        public string $name,
        public ?string $phone,
        public ?string $website,
        public ?string $address,
        public ?string $city,
        public ?string $state,
        public ?string $countryCode,
        public ?string $countryName,
        public string $category,
        public string $categoryLabel,
        public ?string $sourceUrl,
        public bool $likelyChain,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'source_name' => $this->sourceName,
            'external_id' => $this->externalId,
            'name' => $this->name,
            'phone' => $this->phone,
            'website' => $this->website,
            'address' => $this->address,
            'city' => $this->city,
            'state' => $this->state,
            'country_code' => $this->countryCode,
            'country_name' => $this->countryName,
            'category' => $this->category,
            'category_label' => $this->categoryLabel,
            'source_url' => $this->sourceUrl,
            'likely_chain' => $this->likelyChain,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            provider: (string) $row['provider'],
            sourceName: (string) $row['source_name'],
            externalId: (string) $row['external_id'],
            name: (string) $row['name'],
            phone: isset($row['phone']) ? (string) $row['phone'] : null,
            website: isset($row['website']) ? (string) $row['website'] : null,
            address: isset($row['address']) ? (string) $row['address'] : null,
            city: isset($row['city']) ? (string) $row['city'] : null,
            state: isset($row['state']) ? (string) $row['state'] : null,
            countryCode: isset($row['country_code']) ? (string) $row['country_code'] : null,
            countryName: isset($row['country_name']) ? (string) $row['country_name'] : null,
            category: (string) $row['category'],
            categoryLabel: (string) $row['category_label'],
            sourceUrl: isset($row['source_url']) ? (string) $row['source_url'] : null,
            likelyChain: (bool) ($row['likely_chain'] ?? false),
        );
    }
}
