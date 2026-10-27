<?php

declare(strict_types=1);

namespace App\Domain\Integrations;

use App\Domain\Integrations\Contracts\LeadAdsProvider;
use App\Domain\Integrations\Enums\LeadAdsPlatform;
use App\Domain\Integrations\Providers\LinkedInLeadSyncProvider;
use App\Domain\Integrations\Providers\MetaLeadAdsProvider;

/**
 * Resolves the provider for a platform, so callers never name a concrete class.
 */
final class LeadAdsProviders
{
    public function for(LeadAdsPlatform $platform): LeadAdsProvider
    {
        return app(match ($platform) {
            LeadAdsPlatform::Meta => MetaLeadAdsProvider::class,
            LeadAdsPlatform::LinkedIn => LinkedInLeadSyncProvider::class,
        });
    }
}
