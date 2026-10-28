<?php

declare(strict_types=1);

namespace App\Domain\Discovery;

/**
 * Public fields taken from a directory, reduced to what a lead may store.
 */
final class BusinessFields
{
    public static function name(?string $name): ?string
    {
        if ($name === null) {
            return null;
        }

        $trimmed = trim($name);

        if ($trimmed === '') {
            return null;
        }

        return mb_substr($trimmed, 0, 160);
    }

    public static function phone(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $trimmed = trim($phone);
        $digits = preg_replace('/\D+/', '', $trimmed) ?? '';

        if (strlen($digits) < 6) {
            return null;
        }

        return mb_substr($trimmed, 0, 40);
    }

    public static function website(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        $trimmed = trim($url);

        if (filter_var($trimmed, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        $scheme = parse_url($trimmed, PHP_URL_SCHEME);

        if (! in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        return mb_substr($trimmed, 0, 2048);
    }

    public static function line(?string $value, int $limit = 160): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : mb_substr($trimmed, 0, $limit);
    }
}
