<?php

declare(strict_types=1);

namespace App\Domain\Tenders;

/**
 * The government portals this CRM reads, and how a bid on each one is filed.
 */
final class TenderPortal
{
    /**
     * @return list<array{value: string, label: string, country: string}>
     */
    public static function catalog(): array
    {
        return [
            ['value' => 'ppra', 'label' => 'Pakistan PPRA', 'country' => 'Pakistan'],
            ['value' => 'find-a-tender', 'label' => 'UK Find a Tender', 'country' => 'United Kingdom'],
            ['value' => 'contracts-finder', 'label' => 'UK Contracts Finder', 'country' => 'United Kingdom'],
            ['value' => 'public-contracts-scotland', 'label' => 'Public Contracts Scotland', 'country' => 'United Kingdom'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        $labels = [];

        foreach (self::catalog() as $portal) {
            $labels[$portal['value']] = $portal['label'];
        }

        return $labels;
    }

    /**
     * @return list<array{value: string, label: string, sources: list<string>}>
     */
    public static function countries(): array
    {
        $grouped = [];

        foreach (self::catalog() as $portal) {
            $grouped[$portal['country']][] = $portal['value'];
        }

        $countries = [];

        foreach ($grouped as $country => $sources) {
            $countries[] = ['value' => $country, 'label' => $country, 'sources' => $sources];
        }

        return $countries;
    }

    public static function label(string $source): string
    {
        return self::labels()[$source] ?? $source;
    }

    public static function country(string $source): string
    {
        return $source === 'ppra' ? 'Pakistan' : 'United Kingdom';
    }

    public static function timezone(string $source): string
    {
        return $source === 'ppra' ? 'Asia/Karachi' : 'Europe/London';
    }

    /** Where the bidding documents actually live. */
    public static function submitVia(string $source): string
    {
        return match ($source) {
            'ppra' => 'EPADS',
            'contracts-finder' => 'Contracts Finder',
            'public-contracts-scotland' => 'Public Contracts Scotland',
            default => 'Find a Tender',
        };
    }

    /** Things to confirm before bidding. Never stated as facts already held. */
    public static function checklist(string $source): string
    {
        return match ($source) {
            'ppra' => 'confirm FBR active-taxpayer status, NTN/STN, PPRA/EPADS registration, required past-experience evidence and bid security.',
            'public-contracts-scotland' => 'confirm eligibility to bid, required insurance, exclusion grounds and whether an SPD is required.',
            'contracts-finder' => 'confirm the selection questionnaire, required insurance, exclusion grounds and the submission route on Contracts Finder.',
            default => 'confirm Companies House standing, required insurance, exclusion grounds and the selection questionnaire on Find a Tender.',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::labels() as $value => $label) {
            $options[] = ['value' => $value, 'label' => $label];
        }

        return $options;
    }
}
