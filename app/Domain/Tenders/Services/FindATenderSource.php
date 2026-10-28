<?php

declare(strict_types=1);

namespace App\Domain\Tenders\Services;

use App\Domain\Tenders\Contracts\TenderSource;
use App\Domain\Tenders\DataObjects\TenderNotice;

/**
 * UK above-threshold notices from Find a Tender (Cabinet Office).
 *
 * The OCDS release-package endpoint is public and needs no API key.
 *
 * @see https://www.find-tender.service.gov.uk/Developer/Documentation
 */
final class FindATenderSource implements TenderSource
{
    public const SOURCE = 'find-a-tender';

    public function key(): string
    {
        return self::SOURCE;
    }

    /**
     * @return iterable<TenderNotice>
     */
    public function fetchAll(): iterable
    {
        return (new OcdsTenderFeed(
            source: self::SOURCE,
            country: 'United Kingdom',
            endpoint: 'https://www.find-tender.service.gov.uk/api/1.0/ocdsReleasePackages',
            fromParameter: 'updatedFrom',
            noticeUrl: 'https://www.find-tender.service.gov.uk/Notice',
        ))->fetch();
    }
}
