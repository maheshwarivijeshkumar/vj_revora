<?php

declare(strict_types=1);

namespace App\Domain\Tenders\DataObjects;

use Carbon\CarbonImmutable;

/**
 * One tender as a source listed it, before it is classified and stored.
 */
final readonly class TenderNotice
{
    public function __construct(
        public string $source,
        public string $country,
        public string $reference,
        public string $title,
        public string $url,
        public ?string $description = null,
        public ?string $buyerReference = null,
        public ?string $organization = null,
        public ?string $department = null,
        public ?string $city = null,
        public ?string $sector = null,
        public ?string $procurementCategory = null,
        public ?string $tenderType = null,
        public ?string $method = null,
        public ?string $nature = null,
        public ?string $status = null,
        public ?CarbonImmutable $advertisedOn = null,
        public ?CarbonImmutable $closingAt = null,
    ) {}
}
