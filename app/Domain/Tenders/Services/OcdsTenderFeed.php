<?php

declare(strict_types=1);

namespace App\Domain\Tenders\Services;

use App\Domain\Tenders\DataObjects\TenderNotice;
use Carbon\CarbonImmutable;
use Illuminate\Support\Sleep;
use RuntimeException;
use Throwable;

/**
 * Open Contracting Data Standard release packages.
 *
 * Find a Tender and Contracts Finder both publish this shape. A run reads
 * contract notices updated in the lookback window, one page at a time, and
 * stops when the portal says there is no next page.
 *
 * @see https://www.find-tender.service.gov.uk/apidocumentation/1.0/GET-ocdsReleasePackages
 * @see https://www.contractsfinder.service.gov.uk/apidocumentation/Notices/1/GET-Published-Notice-OCDS-Search
 */
final class OcdsTenderFeed
{
    private const LOOKBACK_DAYS = 45;

    private const PAGE_SIZE = 100;

    /** Headroom for a busy month. A portal with fewer notices ends on an empty next link. */
    private const MAX_PAGES = 40;

    public function __construct(
        private string $source,
        private string $country,
        private string $endpoint,
        private string $fromParameter,
        private string $noticeUrl,
    ) {}

    /**
     * @return iterable<TenderNotice>
     */
    public function fetch(): iterable
    {
        $from = CarbonImmutable::now('UTC')->subDays(self::LOOKBACK_DAYS)->format('Y-m-d\TH:i:s');
        $url = $this->endpoint.'?'.http_build_query([
            'stages' => 'tender',
            'limit' => self::PAGE_SIZE,
            $this->fromParameter => $from,
        ]);

        $seen = [];

        for ($page = 1; $page <= self::MAX_PAGES; $page++) {
            if ($page > 1) {
                Sleep::for(1)->second();
            }

            $package = TenderHttp::json($url);
            $releases = $package['releases'] ?? null;

            if (! is_array($releases)) {
                if ($page === 1) {
                    throw new RuntimeException('The tender feed did not return a release list.');
                }

                return;
            }

            $yielded = false;

            foreach ($releases as $release) {
                if (! is_array($release)) {
                    continue;
                }

                $notice = $this->notice($release);

                if ($notice === null || isset($seen[$notice->reference])) {
                    continue;
                }

                $seen[$notice->reference] = true;
                $yielded = true;

                yield $notice;
            }

            $next = $package['links']['next'] ?? null;

            if (! $yielded || ! is_string($next) || $next === '') {
                return;
            }

            $url = $next;
        }
    }

    /**
     * @param  array<string, mixed>  $release
     */
    private function notice(array $release): ?TenderNotice
    {
        $tender = $release['tender'] ?? null;
        $reference = $release['ocid'] ?? null;
        $title = is_array($tender) ? ($tender['title'] ?? null) : null;

        if (! is_string($reference) || $reference === '' || ! is_string($title) || trim($title) === '') {
            return null;
        }

        $buyer = $this->buyer($release);
        $classification = is_array($tender) ? ($tender['classification'] ?? null) : null;
        $published = is_string($release['date'] ?? null) ? $release['date'] : null;
        $closing = is_array($tender) ? ($tender['tenderPeriod']['endDate'] ?? null) : null;

        return new TenderNotice(
            source: $this->source,
            country: $this->country,
            reference: $reference,
            title: trim($title),
            url: $this->url($release),
            description: $this->text(is_array($tender) ? ($tender['description'] ?? null) : null),
            organization: $this->text($buyer['name'] ?? ($release['buyer']['name'] ?? null)),
            city: $this->text($buyer['address']['locality'] ?? null),
            sector: $this->text(is_array($classification) ? ($classification['description'] ?? null) : null),
            procurementCategory: $this->text(is_array($tender) ? ($tender['mainProcurementCategory'] ?? null) : null),
            tenderType: 'Contract notice',
            method: $this->text(is_array($tender) ? ($tender['procurementMethodDetails'] ?? $tender['procurementMethod'] ?? null) : null),
            status: $this->text(is_array($tender) ? ($tender['status'] ?? null) : null),
            advertisedOn: $this->calendarDay(is_string($published) ? $published : null),
            closingAt: $this->instant(is_string($closing) ? $closing : null),
        );
    }

    /**
     * @param  array<string, mixed>  $release
     * @return array<string, mixed>
     */
    private function buyer(array $release): array
    {
        $buyerId = $release['buyer']['id'] ?? null;
        $parties = $release['parties'] ?? [];

        if (! is_array($parties)) {
            return [];
        }

        foreach ($parties as $party) {
            if (! is_array($party)) {
                continue;
            }

            $roles = $party['roles'] ?? [];
            $isBuyer = ($buyerId !== null && ($party['id'] ?? null) === $buyerId)
                || (is_array($roles) && in_array('buyer', $roles, true));

            if ($isBuyer) {
                return $party;
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $release
     */
    private function url(array $release): string
    {
        $documents = $release['tender']['documents'] ?? [];

        if (is_array($documents)) {
            foreach ($documents as $document) {
                $url = is_array($document) ? ($document['url'] ?? null) : null;

                if (is_string($url) && str_contains($url, '/Notice/')) {
                    return $url;
                }
            }
        }

        $id = $release['id'] ?? '';

        if (is_string($id) && $id !== '') {
            return $this->noticeUrl.'/'.rawurlencode($id);
        }

        return $this->noticeUrl;
    }

    private function text(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $clean = trim((string) preg_replace('/\s+/u', ' ', $value));

        return $clean === '' ? null : $clean;
    }

    private function calendarDay(?string $iso): ?CarbonImmutable
    {
        if ($iso === null || $iso === '') {
            return null;
        }

        try {
            $day = CarbonImmutable::parse($iso)->timezone('Europe/London')->toDateString();

            return CarbonImmutable::parse($day)->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }

    private function instant(?string $iso): ?CarbonImmutable
    {
        if ($iso === null || $iso === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($iso)->utc();
        } catch (Throwable) {
            return null;
        }
    }
}
