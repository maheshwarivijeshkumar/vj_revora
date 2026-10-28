<?php

declare(strict_types=1);

namespace App\Domain\Discovery;

/**
 * Local-business categories the discovery search can ask for.
 *
 * Each entry is a kind of independent business the workspace sells to.
 * The OpenStreetMap tags are fixed here so a search cannot inject its own query.
 */
final class BusinessCatalog
{
    /**
     * Names that usually belong to a large chain. Used only to sort those
     * results after independent businesses. A match is a signal, not proof.
     *
     * @var list<string>
     */
    private const CHAINS = [
        'mcdonald',
        'starbucks',
        'kfc',
        'subway',
        'burger king',
        'pizza hut',
        'domino',
        'walmart',
        'costa coffee',
        'dunkin',
        'tim hortons',
        "nando's",
        'nandos',
        'ikea',
        'harvey norman',
    ];

    /**
     * @return list<array{key: string, label: string, group: string, osm: list<array{0: string, 1: string}>}>
     */
    public static function all(): array
    {
        return [
            self::entry('cafe', 'Cafés and coffee shops', 'Food and hospitality', [['amenity', 'cafe']]),
            self::entry('restaurant', 'Restaurants and takeaways', 'Food and hospitality', [['amenity', 'restaurant']]),
            self::entry('fast_food', 'Fast-food shops', 'Food and hospitality', [['amenity', 'fast_food']]),
            self::entry('bakery', 'Bakeries and cake shops', 'Food and hospitality', [['shop', 'bakery']]),
            self::entry('grocery', 'Grocery and convenience stores', 'Food and hospitality', [['shop', 'convenience'], ['shop', 'supermarket']]),
            self::entry('butcher', 'Butchers and specialty food', 'Food and hospitality', [['shop', 'butcher']]),
            self::entry('hair', 'Hair salons and barbers', 'Beauty and wellness', [['shop', 'hairdresser']]),
            self::entry('beauty', 'Beauty salons', 'Beauty and wellness', [['shop', 'beauty']]),
            self::entry('spa', 'Spas and massage', 'Beauty and wellness', [['leisure', 'spa'], ['shop', 'massage']]),
            self::entry('gym', 'Fitness studios and gyms', 'Beauty and wellness', [['leisure', 'fitness_centre']]),
            self::entry('dentist', 'Dental clinics', 'Healthcare', [['amenity', 'dentist']]),
            self::entry('clinic', 'Medical clinics', 'Healthcare', [['amenity', 'clinic'], ['amenity', 'doctors']]),
            self::entry('optician', 'Opticians', 'Healthcare', [['shop', 'optician']]),
            self::entry('vet', 'Veterinary clinics', 'Healthcare', [['amenity', 'veterinary']]),
            self::entry('mechanic', 'Car mechanics and workshops', 'Automotive', [['shop', 'car_repair']]),
            self::entry('car_wash', 'Car washes and detailing', 'Automotive', [['amenity', 'car_wash']]),
            self::entry('tyres', 'Tire and wheel shops', 'Automotive', [['shop', 'tyres']]),
            self::entry('plumber', 'Plumbers', 'Home and trades', [['craft', 'plumber']]),
            self::entry('electrician', 'Electricians', 'Home and trades', [['craft', 'electrician']]),
            self::entry('carpenter', 'Carpenters', 'Home and trades', [['craft', 'carpenter']]),
            self::entry('painter', 'Painters and decorators', 'Home and trades', [['craft', 'painter']]),
            self::entry('cleaning', 'Cleaning companies', 'Home and trades', [['shop', 'dry_cleaning']]),
            self::entry('gardener', 'Landscaping and gardening', 'Home and trades', [['craft', 'gardener']]),
            self::entry('clothes', 'Clothing and footwear', 'Retail', [['shop', 'clothes']]),
            self::entry('electronics', 'Electronics shops', 'Retail', [['shop', 'electronics']]),
            self::entry('mobile', 'Mobile phone shops', 'Retail', [['shop', 'mobile_phone']]),
            self::entry('furniture', 'Furniture stores', 'Retail', [['shop', 'furniture']]),
            self::entry('hardware', 'Hardware shops', 'Retail', [['shop', 'hardware']]),
            self::entry('books', 'Bookshops and stationery', 'Retail', [['shop', 'books']]),
            self::entry('pet', 'Pet shops', 'Retail', [['shop', 'pet']]),
            self::entry('pharmacy', 'Pharmacies', 'Retail', [['amenity', 'pharmacy']]),
            self::entry('accountant', 'Accountants', 'Professional services', [['office', 'accountant']]),
            self::entry('lawyer', 'Law firms', 'Professional services', [['office', 'lawyer']]),
            self::entry('estate', 'Property agents', 'Professional services', [['office', 'estate_agent']]),
            self::entry('photographer', 'Photography studios', 'Professional services', [['shop', 'photo']]),
            self::entry('travel', 'Travel agencies', 'Professional services', [['shop', 'travel_agency']]),
            self::entry('driving_school', 'Driving schools', 'Professional services', [['amenity', 'driving_school']]),
            self::entry('hotel', 'Hotels and guesthouses', 'Other local businesses', [['tourism', 'hotel'], ['tourism', 'guest_house']]),
            self::entry('laundry', 'Laundry and dry cleaning', 'Other local businesses', [['shop', 'laundry'], ['shop', 'dry_cleaning']]),
            self::entry('tailor', 'Tailors', 'Other local businesses', [['shop', 'tailor']]),
        ];
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_column(self::all(), 'key');
    }

    /**
     * @return array{key: string, label: string, group: string, osm: list<array{0: string, 1: string}>}|null
     */
    public static function find(string $key): ?array
    {
        foreach (self::all() as $category) {
            if ($category['key'] === $key) {
                return $category;
            }
        }

        return null;
    }

    public static function likelyChain(string $name): bool
    {
        $haystack = mb_strtolower($name);

        foreach (self::CHAINS as $chain) {
            if (str_contains($haystack, $chain)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array{0: string, 1: string}>  $osm
     * @return array{key: string, label: string, group: string, osm: list<array{0: string, 1: string}>}
     */
    private static function entry(string $key, string $label, string $group, array $osm): array
    {
        return ['key' => $key, 'label' => $label, 'group' => $group, 'osm' => $osm];
    }
}
