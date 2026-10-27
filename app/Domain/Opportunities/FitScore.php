<?php

declare(strict_types=1);

namespace App\Domain\Opportunities;

/**
 * How well an opportunity fits a service profile, with the reasons (§19).
 */
final readonly class FitScore
{
    /**
     * @param  list<string>  $services  profile services the post asks for
     * @param  list<string>  $keywords  the words that matched
     * @param  list<string>  $reasons  plain-language reasons, shown on hover
     */
    public function __construct(
        public int $score,
        public array $services,
        public array $keywords,
        public array $reasons,
        public int $skillHits = 0,
    ) {}

    /**
     * @return array{services: list<string>, keywords: list<string>, reasons: list<string>}
     */
    public function toArray(): array
    {
        return ['services' => $this->services, 'keywords' => $this->keywords, 'reasons' => $this->reasons];
    }
}
