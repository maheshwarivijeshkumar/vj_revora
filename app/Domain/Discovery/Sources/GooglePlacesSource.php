<?php

declare(strict_types=1);

namespace App\Domain\Discovery\Sources;

use App\Domain\Discovery\BusinessCatalog;
use App\Domain\Discovery\BusinessFields;
use App\Domain\Discovery\CountryCode;
use App\Domain\Discovery\DataObjects\DiscoveredBusiness;
use App\Domain\Discovery\DataObjects\DiscoveryQuery;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Local businesses from the Google Places API (Text Search).
 *
 * Nothing is requested unless GOOGLE_PLACES_API_KEY is set. The key is an
 * authorized Places key, not a scrape of Google Maps.
 */
final class GooglePlacesSource
{
    public function isConfigured(): bool
    {
        return filled(config('services.google.places_key'));
    }

    /**
     * @return array{businesses: list<DiscoveredBusiness>, notice: string|null}
     */
    public function search(DiscoveryQuery $query): array
    {
        if (! $this->isConfigured()) {
            return [
                'businesses' => [],
                'notice' => 'Google Maps needs a Places API key (GOOGLE_PLACES_API_KEY) before it can search.',
            ];
        }

        $category = BusinessCatalog::find($query->category);

        if ($category === null) {
            return ['businesses' => [], 'notice' => 'That business category is not available.'];
        }

        $region = CountryCode::toAlpha2($query->country);

        try {
            $response = Http::withHeaders([
                'X-Goog-Api-Key' => (string) config('services.google.places_key'),
                'X-Goog-FieldMask' => 'places.id,places.displayName,places.formattedAddress,places.nationalPhoneNumber,places.internationalPhoneNumber,places.websiteUri,places.googleMapsUri,places.addressComponents,places.businessStatus',
            ])
                ->connectTimeout(5)
                ->timeout(12)
                ->retry(2, 200, function (Throwable $exception): bool {
                    return $exception instanceof ConnectionException
                        || ($exception instanceof RequestException
                            && ($exception->response->serverError() || $exception->response->status() === 429));
                })
                ->post('https://places.googleapis.com/v1/places:searchText', array_filter([
                    'textQuery' => $category['label'].' in '.$query->place(),
                    'pageSize' => 20,
                    'languageCode' => 'en',
                    'regionCode' => $region,
                ]))
                ->throw()
                ->json();
        } catch (ConnectionException|RequestException) {
            return [
                'businesses' => [],
                'notice' => 'Google Maps did not answer. Check the Places API key and try again.',
            ];
        }

        $places = is_array($response) ? ($response['places'] ?? []) : [];

        if (! is_array($places)) {
            return ['businesses' => [], 'notice' => null];
        }

        $businesses = [];

        foreach ($places as $place) {
            if (! is_array($place)) {
                continue;
            }

            $business = $this->map($place, $query, $category);

            if ($business !== null) {
                $businesses[] = $business;
            }
        }

        return ['businesses' => $businesses, 'notice' => null];
    }

    /**
     * @param  array<string, mixed>  $place
     * @param  array{key: string, label: string, group: string, osm: list<array{0: string, 1: string}>}  $category
     */
    private function map(array $place, DiscoveryQuery $query, array $category): ?DiscoveredBusiness
    {
        if (($place['businessStatus'] ?? null) === 'CLOSED_PERMANENTLY') {
            return null;
        }

        $name = BusinessFields::name(is_array($place['displayName'] ?? null) ? ($place['displayName']['text'] ?? null) : null);
        $id = is_string($place['id'] ?? null) ? $place['id'] : null;

        if ($name === null || $id === null || $id === '') {
            return null;
        }

        $address = $this->addressParts(is_array($place['addressComponents'] ?? null) ? $place['addressComponents'] : []);

        return new DiscoveredBusiness(
            provider: 'google_places',
            sourceName: 'Google Maps',
            externalId: mb_substr($id, 0, 255),
            name: $name,
            phone: BusinessFields::phone(
                is_string($place['internationalPhoneNumber'] ?? null)
                    ? $place['internationalPhoneNumber']
                    : (is_string($place['nationalPhoneNumber'] ?? null) ? $place['nationalPhoneNumber'] : null),
            ),
            website: BusinessFields::website(is_string($place['websiteUri'] ?? null) ? $place['websiteUri'] : null),
            address: BusinessFields::line(is_string($place['formattedAddress'] ?? null) ? $place['formattedAddress'] : null, 255),
            city: $address['city'] ?? $query->city,
            state: $address['state'] ?? $query->state,
            countryCode: $address['country_code'] ?? CountryCode::toAlpha2($query->country),
            countryName: $address['country_name'] ?? $query->country,
            category: $category['key'],
            categoryLabel: $category['label'],
            sourceUrl: BusinessFields::website(is_string($place['googleMapsUri'] ?? null) ? $place['googleMapsUri'] : null),
            likelyChain: BusinessCatalog::likelyChain($name),
        );
    }

    /**
     * @param  list<mixed>  $components
     * @return array{city: ?string, state: ?string, country_code: ?string, country_name: ?string}
     */
    private function addressParts(array $components): array
    {
        $city = null;
        $state = null;
        $countryCode = null;
        $countryName = null;

        foreach ($components as $component) {
            if (! is_array($component)) {
                continue;
            }

            $types = is_array($component['types'] ?? null) ? $component['types'] : [];
            $long = is_string($component['longText'] ?? null) ? $component['longText'] : null;
            $short = is_string($component['shortText'] ?? null) ? $component['shortText'] : null;

            if ($city === null && (in_array('locality', $types, true) || in_array('postal_town', $types, true))) {
                $city = BusinessFields::line($long);
            }

            if ($state === null && in_array('administrative_area_level_1', $types, true)) {
                $state = BusinessFields::line($long);
            }

            if ($countryCode === null && in_array('country', $types, true)) {
                $countryCode = CountryCode::toAlpha2($short);
                $countryName = BusinessFields::line($long);
            }
        }

        return [
            'city' => $city,
            'state' => $state,
            'country_code' => $countryCode,
            'country_name' => $countryName,
        ];
    }
}
