<?php

declare(strict_types=1);

namespace App\Domain\Tenders\Services;

use App\Domain\Tenders\DataObjects\TenderNotice;
use App\Domain\Tenders\Enums\TenderTopic;

/**
 * Sorts tenders into web, software, IT or other by keyword and sector.
 *
 * Checked most specific first: a "web portal for the HR system" is web work
 * before it is software, and both before generic IT. Deliberately a keyword
 * list rather than anything clever, so a wrong label is explainable and the
 * fix is adding a word.
 *
 * Reads the title only. Descriptions are often pasted bidding instructions
 * ("request documents through the SAP Ariba Portal") and buyer references
 * carry office codes like "CLP/IT/1213", both of which matched as IT work
 * on live data when they were included.
 */
final class TenderClassifier
{
    /** PPRA's own sector for ICT procurement. */
    private const IT_SECTOR = 'info and comm tech';

    private const WEB = [
        'website', 'web site', 'web portal', 'web-based', 'web based', 'web application',
        'web applications', 'web app', 'web development', 'web hosting', 'portal',
        'e-commerce', 'ecommerce', 'cms', 'wordpress', 'domain name',
    ];

    private const SOFTWARE = [
        'software', 'application development', 'mobile app', 'mobile application',
        'android', 'erp', 'mis', 'management information system', 'information system',
        'database', 'dashboard', 'digitization', 'digitisation', 'digitalization',
        'automation', 'e-office', 'lms', 'hrms', 'hris', 'oracle', 'sap', 'saas',
        'cloud', 'api', 'system development', 'online system', 'gis', 'e-governance',
        'artificial intelligence', 'machine learning', 'chatbot',
    ];

    private const IT = [
        'ict', 'computer', 'computers', 'laptop', 'laptops', 'desktop', 'desktops',
        'server', 'servers', 'networking', 'network equipment', 'lan', 'wan', 'wifi',
        'wi-fi', 'firewall', 'cyber security', 'cybersecurity', 'vapt',
        'penetration testing', 'data center', 'data centre', 'printer', 'printers',
        'scanner', 'scanners', 'cctv', 'it equipment', 'ip phone', 'fiber optic',
        'fibre optic', 'storage area network', 'switches', 'routers', 'tablet',
        'tablets', 'it services', 'information technology', 'telecom',
    ];

    public function classify(TenderNotice $notice): TenderTopic
    {
        $text = $notice->title;

        if ($this->mentions($text, self::WEB)) {
            return TenderTopic::Web;
        }

        if ($this->mentions($text, self::SOFTWARE)) {
            return TenderTopic::Software;
        }

        // "IT" is matched in capitals only, or every "it" in English counts.
        if (str_contains(mb_strtolower((string) $notice->sector), self::IT_SECTOR)
            || preg_match('/\bI\.?T\b/', $text) === 1
            || $this->mentions($text, self::IT)) {
            return TenderTopic::InformationTechnology;
        }

        return TenderTopic::Other;
    }

    /**
     * @param  list<string>  $keywords
     */
    private function mentions(string $text, array $keywords): bool
    {
        $pattern = '/\b(?:'.implode('|', array_map(fn (string $k): string => preg_quote($k, '/'), $keywords)).')\b/i';

        return preg_match($pattern, $text) === 1;
    }
}
