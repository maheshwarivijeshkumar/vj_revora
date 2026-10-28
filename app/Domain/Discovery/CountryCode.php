<?php

declare(strict_types=1);

namespace App\Domain\Discovery;

/**
 * Turns a country a person typed into the two-letter code leads already store.
 */
final class CountryCode
{
    /**
     * @var array<string, string>
     */
    private const NAMES = [
        'pakistan' => 'PK',
        'united arab emirates' => 'AE',
        'uae' => 'AE',
        'u.a.e.' => 'AE',
        'india' => 'IN',
        'united kingdom' => 'GB',
        'uk' => 'GB',
        'great britain' => 'GB',
        'england' => 'GB',
        'scotland' => 'GB',
        'wales' => 'GB',
        'united states' => 'US',
        'united states of america' => 'US',
        'usa' => 'US',
        'us' => 'US',
        'canada' => 'CA',
        'australia' => 'AU',
        'new zealand' => 'NZ',
        'ireland' => 'IE',
        'germany' => 'DE',
        'france' => 'FR',
        'spain' => 'ES',
        'italy' => 'IT',
        'netherlands' => 'NL',
        'holland' => 'NL',
        'belgium' => 'BE',
        'switzerland' => 'CH',
        'austria' => 'AT',
        'sweden' => 'SE',
        'norway' => 'NO',
        'denmark' => 'DK',
        'finland' => 'FI',
        'portugal' => 'PT',
        'poland' => 'PL',
        'turkey' => 'TR',
        'türkiye' => 'TR',
        'saudi arabia' => 'SA',
        'qatar' => 'QA',
        'kuwait' => 'KW',
        'bahrain' => 'BH',
        'oman' => 'OM',
        'jordan' => 'JO',
        'lebanon' => 'LB',
        'egypt' => 'EG',
        'south africa' => 'ZA',
        'nigeria' => 'NG',
        'kenya' => 'KE',
        'bangladesh' => 'BD',
        'sri lanka' => 'LK',
        'nepal' => 'NP',
        'china' => 'CN',
        'japan' => 'JP',
        'south korea' => 'KR',
        'singapore' => 'SG',
        'malaysia' => 'MY',
        'indonesia' => 'ID',
        'thailand' => 'TH',
        'philippines' => 'PH',
        'vietnam' => 'VN',
        'brazil' => 'BR',
        'mexico' => 'MX',
        'argentina' => 'AR',
    ];

    public static function toAlpha2(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        if (preg_match('/^[A-Za-z]{2}$/', $trimmed) === 1) {
            return strtoupper($trimmed);
        }

        $key = mb_strtolower($trimmed);

        return self::NAMES[$key] ?? null;
    }
}
