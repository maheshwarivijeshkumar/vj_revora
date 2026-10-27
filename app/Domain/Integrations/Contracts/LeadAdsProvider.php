<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Contracts;

use App\Domain\Integrations\DataObjects\DiscoveredAccount;
use App\Domain\Integrations\DataObjects\ProviderLead;
use App\Domain\Integrations\Enums\LeadAdsPlatform;
use App\Domain\Integrations\Exceptions\ProviderAuthException;
use App\Domain\Integrations\Exceptions\ProviderRequestException;
use App\Models\IntegrationConnection;
use Carbon\CarbonInterface;

/**
 * A lead-ad network reached through its authorized API (§6, §12, §13).
 *
 * The slice of the planned IntegrationProvider that lead sync needs: connect by
 * OAuth, discover the assets the user may read, and page through leads. Every
 * lead it returns goes through the same CaptureLead pipeline as a form or an
 * import, so deduplication and scoring do not depend on where it came from.
 */
interface LeadAdsProvider
{
    public function platform(): LeadAdsPlatform;

    /** Whether the app credentials are set, so a connection can complete. */
    public function isConfigured(): bool;

    public function authorizationUrl(string $state, string $redirectUri): string;

    /**
     * Exchanges the OAuth code and lists the accounts the user can read leads from.
     *
     * @return list<DiscoveredAccount>
     *
     * @throws ProviderAuthException|ProviderRequestException
     */
    public function connect(string $code, string $redirectUri): array;

    /**
     * Leads submitted after $since, oldest first where the provider allows.
     *
     * @return iterable<ProviderLead>
     *
     * @throws ProviderAuthException when the token no longer works.
     * @throws ProviderRequestException for anything a later retry may fix.
     */
    public function fetchLeads(IntegrationConnection $connection, CarbonInterface $since): iterable;
}
