<?php

declare(strict_types=1);

namespace App\Domain\Time;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Time zones in the Windows display form: (UTC+05:00) Islamabad, Karachi.
 *
 * The value stored on the user is still an IANA identifier. The label is
 * what every screen shows.
 */
final class TimezoneCatalog
{
    /**
     * IANA identifier => the cities Windows shows for that offset.
     *
     * @var array<string, string>
     */
    private const ZONES = [
        'Pacific/Midway' => 'Midway Island, Samoa',
        'Pacific/Honolulu' => 'Hawaii',
        'America/Anchorage' => 'Alaska',
        'America/Los_Angeles' => 'Pacific Time (US & Canada)',
        'America/Denver' => 'Mountain Time (US & Canada)',
        'America/Chicago' => 'Central Time (US & Canada)',
        'America/New_York' => 'Eastern Time (US & Canada)',
        'America/Halifax' => 'Atlantic Time (Canada)',
        'America/St_Johns' => 'Newfoundland',
        'America/Sao_Paulo' => 'Brasilia',
        'America/Argentina/Buenos_Aires' => 'Buenos Aires',
        'Atlantic/South_Georgia' => 'Mid-Atlantic',
        'Atlantic/Azores' => 'Azores',
        'UTC' => 'Coordinated Universal Time',
        'Europe/London' => 'Dublin, Edinburgh, Lisbon, London',
        'Europe/Berlin' => 'Amsterdam, Berlin, Bern, Rome, Stockholm, Vienna',
        'Europe/Paris' => 'Brussels, Copenhagen, Madrid, Paris',
        'Europe/Warsaw' => 'Sarajevo, Skopje, Warsaw, Zagreb',
        'Europe/Athens' => 'Athens, Bucharest',
        'Africa/Cairo' => 'Cairo',
        'Europe/Helsinki' => 'Helsinki, Kyiv, Riga, Sofia, Tallinn, Vilnius',
        'Europe/Istanbul' => 'Istanbul',
        'Asia/Jerusalem' => 'Jerusalem',
        'Asia/Baghdad' => 'Baghdad',
        'Asia/Riyadh' => 'Kuwait, Riyadh',
        'Europe/Moscow' => 'Moscow, St. Petersburg',
        'Africa/Nairobi' => 'Nairobi',
        'Asia/Tehran' => 'Tehran',
        'Asia/Dubai' => 'Abu Dhabi, Muscat',
        'Asia/Baku' => 'Baku',
        'Asia/Tbilisi' => 'Tbilisi',
        'Asia/Yerevan' => 'Yerevan',
        'Asia/Kabul' => 'Kabul',
        'Asia/Tashkent' => 'Ashgabat, Tashkent',
        'Asia/Almaty' => 'Astana',
        'Asia/Yekaterinburg' => 'Ekaterinburg',
        'Asia/Karachi' => 'Islamabad, Karachi',
        'Asia/Kolkata' => 'Chennai, Kolkata, Mumbai, New Delhi',
        'Asia/Kathmandu' => 'Kathmandu',
        'Asia/Dhaka' => 'Dhaka',
        'Asia/Bishkek' => 'Bishkek',
        'Asia/Omsk' => 'Omsk',
        'Asia/Yangon' => 'Yangon',
        'Asia/Bangkok' => 'Bangkok, Hanoi, Jakarta',
        'Asia/Novosibirsk' => 'Novosibirsk',
        'Asia/Shanghai' => 'Beijing, Chongqing, Hong Kong, Urumqi',
        'Asia/Singapore' => 'Kuala Lumpur, Singapore',
        'Australia/Perth' => 'Perth',
        'Asia/Taipei' => 'Taipei',
        'Asia/Tokyo' => 'Osaka, Sapporo, Tokyo',
        'Asia/Seoul' => 'Seoul',
        'Australia/Adelaide' => 'Adelaide',
        'Australia/Darwin' => 'Darwin',
        'Australia/Brisbane' => 'Brisbane',
        'Australia/Sydney' => 'Canberra, Melbourne, Sydney',
        'Pacific/Guam' => 'Guam, Port Moresby',
        'Asia/Vladivostok' => 'Vladivostok',
        'Pacific/Noumea' => 'Solomon Islands, New Caledonia',
        'Pacific/Auckland' => 'Auckland, Wellington',
        'Pacific/Fiji' => 'Fiji',
        'Pacific/Tongatapu' => 'Nuku\'alofa',
    ];

    /**
     * @return array<string, string>
     */
    public static function zones(): array
    {
        return self::ZONES;
    }

    public static function label(?string $identifier): string
    {
        if ($identifier === null || $identifier === '') {
            $identifier = 'UTC';
        }

        $cities = self::ZONES[$identifier] ?? str_replace(['_', '/'], [' ', ', '], $identifier);

        return '('.self::offset($identifier).') '.$cities;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::ZONES as $identifier => $cities) {
            $options[] = [
                'value' => $identifier,
                'label' => '('.self::offset($identifier).') '.$cities,
                'offset' => self::seconds($identifier),
            ];
        }

        usort($options, function (array $a, array $b): int {
            return $a['offset'] <=> $b['offset'] ?: strcmp($a['label'], $b['label']);
        });

        return array_map(
            fn (array $option): array => ['value' => $option['value'], 'label' => $option['label']],
            $options,
        );
    }

    public static function knows(string $identifier): bool
    {
        return array_key_exists($identifier, self::ZONES);
    }

    private static function offset(string $identifier): string
    {
        $seconds = self::seconds($identifier);
        $sign = $seconds >= 0 ? '+' : '-';
        $absolute = abs($seconds);

        return sprintf('UTC%s%02d:%02d', $sign, intdiv($absolute, 3600), intdiv($absolute % 3600, 60));
    }

    private static function seconds(string $identifier): int
    {
        $zone = new DateTimeZone(array_key_exists($identifier, self::ZONES) || in_array($identifier, timezone_identifiers_list(), true) ? $identifier : 'UTC');

        return $zone->getOffset(new DateTimeImmutable('now', new DateTimeZone('UTC')));
    }
}
