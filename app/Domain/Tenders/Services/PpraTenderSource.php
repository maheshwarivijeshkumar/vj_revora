<?php

declare(strict_types=1);

namespace App\Domain\Tenders\Services;

use App\Domain\Tenders\DataObjects\TenderNotice;
use Carbon\CarbonImmutable;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use RuntimeException;
use Throwable;

/**
 * Active tenders from PPRA's e-Publish & Monitoring System (EPMS).
 *
 * PPRA publishes federal procurement notices as a public, paginated HTML list
 * with no feed or API, so this reads that list. It is fetched at a gentle
 * pace — one page at a time with a pause between — twice a day.
 *
 * @see https://epms.ppra.gov.pk/public/tenders/active-tenders
 */
final class PpraTenderSource
{
    public const SOURCE = 'ppra';

    private const BASE_URL = 'https://epms.ppra.gov.pk';

    private const LIST_PATH = '/public/tenders/active-tenders';

    /** Dates on the site are Pakistan Standard Time. */
    private const TIMEZONE = 'Asia/Karachi';

    /** ~2,000 active tenders at 50 a page is 40; this is headroom, not a target. */
    private const MAX_PAGES = 120;

    /**
     * Every active tender, page by page.
     *
     * @return iterable<TenderNotice>
     *
     * @throws RuntimeException when the first page cannot be read at all.
     */
    public function fetchAll(): iterable
    {
        $seen = [];

        for ($page = 1; $page <= self::MAX_PAGES; $page++) {
            if ($page > 1) {
                Sleep::for(1)->second();
            }

            $notices = $this->parse($this->fetchPage($page));
            $new = array_filter($notices, fn (TenderNotice $n): bool => ! isset($seen[$n->reference]));

            // An empty page, or one that repeats what we already have (some
            // pagers serve the last page again past the end), means done.
            if ($new === []) {
                return;
            }

            foreach ($new as $notice) {
                $seen[$notice->reference] = true;

                yield $notice;
            }
        }
    }

    /**
     * Reads the tender rows out of one page of the list.
     *
     * @return list<TenderNotice>
     */
    public function parse(string $html): array
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($document);
        $notices = [];

        foreach ($this->nodes($xpath, "//tr[contains(concat(' ', normalize-space(@class), ' '), ' cb-rt-main ')]") as $row) {
            $notice = $this->notice($xpath, $row);

            if ($notice !== null) {
                $notices[] = $notice;
            }
        }

        return $notices;
    }

    private function fetchPage(int $page): string
    {
        try {
            return Http::withUserAgent('RevoraCRM tender monitor (+'.config('app.url').')')
                ->timeout(60)
                ->retry(2, 3000)
                ->get(self::BASE_URL.self::LIST_PATH, ['page' => $page])
                ->throw()
                ->body();
        } catch (ConnectionException|RequestException $e) {
            throw new RuntimeException("Could not read PPRA tenders page {$page}: ".$e->getMessage(), previous: $e);
        }
    }

    private function notice(DOMXPath $xpath, DOMNode $row): ?TenderNotice
    {
        $reference = $this->text($xpath, ".//td[@data-label='Tender No']//strong", $row);
        $title = $this->text($xpath, ".//td[@data-label='Tender Details']//strong", $row);

        if ($reference === null || $title === null) {
            return null;
        }

        $href = $this->attribute($xpath, ".//td[@data-label='Actions']//a[contains(@href, 'tender-details')]", 'href', $row);
        $closingDate = $this->text($xpath, ".//td[@data-label='Closing']//strong", $row);
        $closingTime = $this->text($xpath, ".//td[@data-label='Closing']//small", $row);

        $detail = $this->first($xpath, "following-sibling::tr[1][contains(@class, 'cb-rt-detail')]", $row);
        $fields = $detail === null ? [] : $this->detailFields($xpath, $detail);

        return new TenderNotice(
            reference: $reference,
            title: $title,
            url: $href === null ? self::BASE_URL.'/public/tenders/tender-details/'.rawurlencode($reference) : $this->absolute($href),
            description: $this->text($xpath, ".//td[@data-label='Tender Details']//small[contains(@class, 'd-block')]", $row),
            buyerReference: $this->text($xpath, ".//td[@data-label='Tender Details']//small[contains(@class, 'text-muted') and contains(@class, 'badge')]", $row),
            organization: $fields['organization'] ?? null,
            department: $fields['department'] ?? null,
            city: $fields['city'] ?? null,
            sector: $fields['Sector'] ?? $this->text($xpath, ".//td[@data-label='Tender Details']//small[contains(@class, 'badge') and not(contains(@class, 'text-muted'))]", $row),
            procurementCategory: $fields['Procurement Category'] ?? null,
            tenderType: $fields['Tender Type'] ?? null,
            method: $this->text($xpath, ".//td[@data-label='Tender No']//span[contains(@class, 'badge')]", $row),
            nature: $fields['Tender Nature'] ?? null,
            status: $this->text($xpath, ".//td[@data-label='Status']", $row),
            advertisedOn: $this->day($this->text($xpath, ".//td[@data-label='Advertised']", $row)),
            closingAt: $this->date(trim(($closingDate ?? '').' '.($closingTime ?? ''))),
        );
    }

    /**
     * The expandable detail card: label => value, with the organization block
     * split into ministry, department and city.
     *
     * @return array<string, string>
     */
    private function detailFields(DOMXPath $xpath, DOMNode $detail): array
    {
        $fields = [];

        foreach ($this->nodes($xpath, ".//span[contains(@class, 'cb-rt-dl')]", $detail) as $label) {
            $name = $this->clean($label->textContent);
            $value = $this->first($xpath, "following-sibling::span[contains(@class, 'cb-rt-dv')][1]", $label);

            if ($name === null || $value === null) {
                continue;
            }

            if ($name === 'Organization') {
                $fields['organization'] = $this->text($xpath, './/strong', $value) ?? '';
                $fields['department'] = $this->text($xpath, ".//span[contains(@class, 'd-block')]", $value) ?? '';
                $city = $this->text($xpath, './/small', $value);
                $fields['city'] = $city === null ? '' : (string) preg_replace('/\s*-\s*Pakistan$/i', '', $city);

                continue;
            }

            $fields[$name] = $this->clean($value->textContent) ?? '';
        }

        return array_filter($fields, fn (string $v): bool => $v !== '');
    }

    private function text(DOMXPath $xpath, string $query, DOMNode $context): ?string
    {
        $node = $this->first($xpath, $query, $context);

        if ($node === null) {
            return null;
        }

        // A <br> inside a title is a line break, not the absence of a space.
        foreach (iterator_to_array($node->getElementsByTagName('br')) as $br) {
            $br->parentNode?->replaceChild($node->ownerDocument?->createTextNode(' ') ?? $br, $br);
        }

        return $this->clean($node->textContent);
    }

    private function attribute(DOMXPath $xpath, string $query, string $attribute, DOMNode $context): ?string
    {
        $node = $this->first($xpath, $query, $context);

        return $node instanceof DOMElement && $node->hasAttribute($attribute) ? $node->getAttribute($attribute) : null;
    }

    /**
     * Element matches only: text and attribute nodes are never what a row
     * lookup wants, and narrowing here keeps every caller simple.
     *
     * @return list<DOMElement>
     */
    private function nodes(DOMXPath $xpath, string $query, ?DOMNode $context = null): array
    {
        $result = $xpath->query($query, $context);

        if ($result === false) {
            return [];
        }

        $elements = [];

        foreach ($result as $node) {
            if ($node instanceof DOMElement) {
                $elements[] = $node;
            }
        }

        return $elements;
    }

    private function first(DOMXPath $xpath, string $query, DOMNode $context): ?DOMElement
    {
        return $this->nodes($xpath, $query, $context)[0] ?? null;
    }

    private function clean(string $text): ?string
    {
        $clean = trim((string) preg_replace('/\s+/u', ' ', $text));

        return $clean === '' ? null : $clean;
    }

    private function absolute(string $href): string
    {
        return str_starts_with($href, 'http') ? $href : self::BASE_URL.'/'.ltrim($href, '/');
    }

    /**
     * "Oct 26, 2026" as a calendar day. Not shifted to UTC: a date with no
     * time is the same day everywhere, and shifting it would print the 25th.
     */
    private function day(?string $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }

    /** "Nov 13, 2026 11:00 AM", in Pakistan time, as UTC. */
    private function date(?string $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value, self::TIMEZONE)->utc();
        } catch (Throwable) {
            return null;
        }
    }
}
