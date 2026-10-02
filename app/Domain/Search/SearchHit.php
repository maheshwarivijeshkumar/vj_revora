<?php

declare(strict_types=1);

namespace App\Domain\Search;

/**
 * One row in the palette (§120).
 */
final readonly class SearchHit
{
    public function __construct(
        public string $id,
        public string $title,
        public ?string $subtitle,
        public ?string $badge,
        public string $href,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'badge' => $this->badge,
            'href' => $this->href,
        ];
    }
}
