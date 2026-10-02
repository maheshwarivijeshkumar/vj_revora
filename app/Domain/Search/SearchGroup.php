<?php

declare(strict_types=1);

namespace App\Domain\Search;

/**
 * Results for one entity, kept grouped (§45).
 *
 * Categorised rather than relevance-ranked across types, because "the Acme
 * company" and "the Acme deal" are different answers to the same word and
 * interleaving them makes the user read every row to find which is which.
 */
final readonly class SearchGroup
{
    /**
     * @param  list<SearchHit>  $hits
     */
    public function __construct(
        public string $key,
        public string $label,
        public array $hits,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'hits' => array_map(fn (SearchHit $hit): array => $hit->toArray(), $this->hits),
        ];
    }
}
