<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Enums;

/**
 * The lead-ad networks a workspace can connect (§12, §13).
 *
 * Instagram is not listed separately: Instagram lead ads belong to the Facebook
 * Page that runs them, so connecting the Page brings both in.
 */
enum LeadAdsPlatform: string
{
    case Meta = 'meta';
    case LinkedIn = 'linkedin';

    public function label(): string
    {
        return match ($this) {
            self::Meta => 'Facebook & Instagram Lead Ads',
            self::LinkedIn => 'LinkedIn Lead Gen Forms',
        };
    }

    /** What one connection represents, for the UI. */
    public function accountNoun(): string
    {
        return match ($this) {
            self::Meta => 'Facebook Page',
            self::LinkedIn => 'LinkedIn ad account',
        };
    }
}
