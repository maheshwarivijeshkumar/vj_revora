<?php

declare(strict_types=1);

namespace App\Domain\Opportunities\DataObjects;

use Carbon\CarbonInterface;

/**
 * One job post as a source returned it, before it is stored.
 */
final readonly class JobPostData
{
    /**
     * @param  list<string>  $tags
     * @param  array<string, mixed>  $signals
     */
    public function __construct(
        public string $source,
        public string $externalId,
        public string $title,
        public string $url,
        public ?string $company = null,
        public ?string $description = null,
        public ?string $location = null,
        public bool $isRemote = true,
        public ?string $jobType = null,
        public array $tags = [],
        public ?string $budgetType = null,
        public ?float $budgetMin = null,
        public ?float $budgetMax = null,
        public ?string $currency = null,
        public array $signals = [],
        public ?CarbonInterface $postedAt = null,
    ) {}
}
