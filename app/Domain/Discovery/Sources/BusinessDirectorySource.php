<?php

declare(strict_types=1);

namespace App\Domain\Discovery\Sources;

use App\Domain\Discovery\BusinessCatalog;
use App\Domain\Discovery\BusinessFields;
use App\Domain\Discovery\CountryCode;
use App\Domain\Discovery\DataObjects\DiscoveredBusiness;
use App\Domain\Discovery\DataObjects\DiscoveryQuery;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Local businesses from OpenStreetMap, shown as the Business Directory source.
 *
 * The city is geocoded, then places of the chosen category are read inside
 * an 8 km radius. The user-supplied text never becomes part of the map query.
 */
final class BusinessDirectorySource
{
    private const RADIUS_METRES = 8000;

    /**
     * @return array{businesses: list<DiscoveredBusiness>, notice: string|null}
     */
    public function search(DiscoveryQuery $query): array
    {
        $category = BusinessCatalog::find($query->category);

        if ($category === null) {
            return ['businesses' => [], 'notice' => 'That business category is not available.'];
        }

        $centre = $this->geocode($query);

        if ($centre === null) {
            return [
                'businesses' => [],
                'notice' => 'No place matched that city, state and country. Check the spelling and try again.',
            ];
        }

        try {
            $response = $this->request()
                ->asForm()
                ->post('https://overpass-api.de/api/interpreter', [
                    'data' => $this->overpass($category['osm'], $centre['lat'], $centre['lon']),
                ])
                ->throw()
                ->json();
        } catch (ConnectionException|RequestException) {
            return [
                'businesses' => [],
                'notice' => 'The business directory did not answer. Try again in a moment.',
            ];
        }

        $elements = is_array($response) ? ($response['elements'] ?? []) : [];

        if (! is_array($elements)) {
            return ['businesses' => [], 'notice' => null];
        }

        $businesses = [];

        foreach ($elements as $element) {
            if (! is_array($element)) {
                continue;
            }

            $business = $this->map($element, $query, $category);

            if ($business !== null) {
                $businesses[] = $business;
            }

            if (count($businesses) >= 20) {
                break;
            }
        }

        return ['businesses' => $businesses, 'notice' => null];
    }

    /**
     * @return array{lat: float, lon: float}|null
     */
    private function geocode(DiscoveryQuery $query): ?array
    {
        try {
            $response = $this->request()
                ->get('https://nominatim.openstreetmap.org/search', [
                    'q' => $query->place(),
                    'format' => 'jsonv2',
                    'limit' => 1,
                ])
                ->throw()
                ->json();
        } catch (ConnectionException|RequestException) {
            return null;
        }

        $first = is_array($response) ? ($response[0] ?? null) : null;

        if (! is_array($first) || ! is_numeric($first['lat'] ?? null) || ! is_numeric($first['lon'] ?? null)) {
            return null;
        }

        $lat = (float) $first['lat'];
        $lon = (float) $first['lon'];

        if ($lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) {
            return null;
        }

        return ['lat' => $lat, 'lon' => $lon];
    }

    /**
     * @param  list<array{0: string, 1: string}>  $tags
     */
    private function overpass(array $tags, float $lat, float $lon): string
    {
        $clauses = [];
        $around = sprintf('(around:%d,%.5f,%.5f)', self::RADIUS_METRES, $lat, $lon);

        foreach ($tags as [$key, $value]) {
            if (preg_match('/^[a-z_]+$/', $key) !== 1 || preg_match('/^[a-z_]+$/', $value) !== 1) {
                continue;
            }

            $clauses[] = 'node["'.$key.'"="'.$value.'"]'.$around.';';
            $clauses[] = 'way["'.$key.'"="'.$value.'"]'.$around.';';
        }

        return "[out:json][timeout:20];\n(\n".implode("\n", $clauses)."\n);\nout center 25;";
    }

    /**
     * @param  array<string, mixed>  $element
     * @param  array{key: string, label: string, group: string, osm: list<array{0: string, 1: string}>}  $category
     */
    private function map(array $element, DiscoveryQuery $query, array $category): ?DiscoveredBusiness
    {
        $tags = is_array($element['tags'] ?? null) ? $element['tags'] : [];
        $name = BusinessFields::name(is_string($tags['name'] ?? null) ? $tags['name'] : null);
        $type = is_string($element['type'] ?? null) ? $element['type'] : null;
        $id = $element['id'] ?? null;

        if ($name === null || ($type !== 'node' && $type !== 'way') || ! is_numeric($id)) {
            return null;
        }

        $country = is_string($tags['addr:country'] ?? null) ? $tags['addr:country'] : $query->country;

        return new DiscoveredBusiness(
            provider: 'openstreetmap',
            sourceName: 'Business Directory',
            externalId: $type.'/'.$id,
            name: $name,
            phone: BusinessFields::phone(is_string($tags['phone'] ?? null) ? $tags['phone'] : (is_string($tags['contact:phone'] ?? null) ? $tags['contact:phone'] : null)),
            website: BusinessFields::website(is_string($tags['website'] ?? null) ? $tags['website'] : (is_string($tags['contact:website'] ?? null) ? $tags['contact:website'] : null)),
            address: BusinessFields::line($this->address($tags), 255),
            city: BusinessFields::line(is_string($tags['addr:city'] ?? null) ? $tags['addr:city'] : null) ?? $query->city,
            state: BusinessFields::line(is_string($tags['addr:state'] ?? null) ? $tags['addr:state'] : null) ?? $query->state,
            countryCode: CountryCode::toAlpha2($country),
            countryName: strlen(trim($country)) === 2 ? $query->country : $country,
            category: $category['key'],
            categoryLabel: $category['label'],
            sourceUrl: 'https://www.openstreetmap.org/'.$type.'/'.$id,
            likelyChain: BusinessCatalog::likelyChain($name),
        );
    }

    /**
     * @param  array<string, mixed>  $tags
     */
    private function address(array $tags): ?string
    {
        $parts = array_filter([
            is_string($tags['addr:housenumber'] ?? null) ? $tags['addr:housenumber'] : null,
            is_string($tags['addr:street'] ?? null) ? $tags['addr:street'] : null,
            is_string($tags['addr:city'] ?? null) ? $tags['addr:city'] : null,
        ], fn (?string $part): bool => $part !== null && trim($part) !== '');

        if ($parts === []) {
            return null;
        }

        return implode(' ', $parts);
    }

    private function request(): PendingRequest
    {
        return Http::withUserAgent(config('app.name').' business discovery (+'.config('app.url').')')
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(20)
            ->retry(2, 200, function (Throwable $exception): bool {
                return $exception instanceof ConnectionException
                    || ($exception instanceof RequestException
                        && ($exception->response->serverError() || $exception->response->status() === 429));
            });
    }
}
