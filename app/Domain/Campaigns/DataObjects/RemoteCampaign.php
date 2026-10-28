<?php

declare(strict_types=1);

namespace App\Domain\Campaigns\DataObjects;

use Carbon\CarbonInterface;

/**
 * One campaign read from an ad account or from a lead's attribution.
 */
final readonly class RemoteCampaign
{
    public function __construct(
        public string $platform,
        public string $externalId,
        public string $name,
        public ?string $status = null,
        public ?string $objective = null,
        public ?CarbonInterface $startsAt = null,
        public ?CarbonInterface $endsAt = null,
    ) {}
}
