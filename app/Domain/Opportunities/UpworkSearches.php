<?php

declare(strict_types=1);

namespace App\Domain\Opportunities;

use App\Models\ServiceProfile;

/**
 * Turns a service profile into Upwork search expressions.
 *
 * One search per profile, OR-ing the first keywords of each service, so a
 * run stays a handful of API calls rather than one per keyword.
 */
final class UpworkSearches
{
    private const TERMS_PER_SERVICE = 2;

    private const MAX_TERMS = 12;

    /**
     * @return list<string>
     */
    public function for(ServiceProfile $profile): array
    {
        $terms = [];

        foreach ($profile->services as $service) {
            foreach (array_slice($service['keywords'] ?? [], 0, self::TERMS_PER_SERVICE) as $keyword) {
                $keyword = trim((string) $keyword);

                if ($keyword !== '') {
                    $terms[] = str_contains($keyword, ' ') ? '"'.str_replace('"', '', $keyword).'"' : $keyword;
                }
            }
        }

        $terms = array_slice(array_values(array_unique($terms)), 0, self::MAX_TERMS);

        return $terms === [] ? [] : [implode(' OR ', $terms)];
    }
}
