<?php

declare(strict_types=1);

namespace App\Domain\Campaigns;

use Illuminate\Support\Str;

/**
 * The networks a campaign can be filed under.
 *
 * Facebook and Instagram share one Meta ad account, so a campaign read from
 * the API is `meta`. A lead that names only one of them stays on that network
 * until the API row arrives.
 */
final class AdPlatform
{
    public static function normalize(string $raw): string
    {
        $value = strtolower(trim($raw));

        $platform = match (true) {
            in_array($value, ['facebook', 'fb'], true) => 'facebook',
            in_array($value, ['meta', 'facebook & instagram'], true) => 'meta',
            in_array($value, ['instagram', 'ig'], true) => 'instagram',
            in_array($value, ['linkedin', 'linked in'], true) => 'linkedin',
            in_array($value, ['google', 'google ads', 'adwords', 'youtube'], true) => 'google',
            in_array($value, ['tiktok', 'tik tok', 'tik_tok'], true) => 'tiktok',
            default => Str::slug($value),
        };

        $platform = $platform !== '' ? $platform : 'other';

        return mb_substr($platform, 0, 32);
    }

    public static function label(string $platform): string
    {
        return match ($platform) {
            'meta' => 'Facebook & Instagram',
            'facebook' => 'Facebook',
            'instagram' => 'Instagram',
            'linkedin' => 'LinkedIn',
            'google' => 'Google',
            'tiktok' => 'TikTok',
            default => Str::headline($platform),
        };
    }
}
