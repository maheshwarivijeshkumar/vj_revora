<?php

declare(strict_types=1);

namespace App\Domain\Tenders\Services;

use App\Domain\Tenders\Contracts\TenderSource;
use App\Domain\Tenders\DataObjects\TenderNotice;

/**
 * UK below-threshold notices from Contracts Finder (Cabinet Office).
 *
 * The published-notice search is public. Posting a notice needs an account;
 * reading one does not.
 *
 * @see https://www.contractsfinder.service.gov.uk/apidocumentation/Notices/1/GET-Published-Notice-OCDS-Search
 */
final class ContractsFinderSource implements TenderSource
{
    public const SOURCE = 'contracts-finder';

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
            endpoint: 'https://www.contractsfinder.service.gov.uk/Published/Notices/OCDS/Search',
            fromParameter: 'publishedFrom',
            noticeUrl: 'https://www.contractsfinder.service.gov.uk/Notice',
        ))->fetch();
    }
}
