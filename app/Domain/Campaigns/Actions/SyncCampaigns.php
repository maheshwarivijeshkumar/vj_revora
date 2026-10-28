<?php

declare(strict_types=1);

namespace App\Domain\Campaigns\Actions;

use App\Domain\Campaigns\AdPlatform;
use App\Domain\Campaigns\DataObjects\RemoteCampaign;
use App\Domain\Integrations\Enums\ConnectionStatus;
use App\Domain\Integrations\Exceptions\ProviderAuthException;
use App\Domain\Integrations\Exceptions\ProviderRequestException;
use App\Domain\Integrations\LeadAdsProviders;
use App\Models\AdCampaign;
use App\Models\IntegrationConnection;
use App\Models\Lead;
use Illuminate\Support\Str;

/**
 * Writes the campaigns a workspace can name.
 *
 * Connected Facebook (and its Instagram placements) and LinkedIn accounts are
 * read from their APIs. Every other network — Google, TikTok, or anything a
 * lead already attributes — is taken from the leads themselves. A campaign
 * read that fails does not change the connection: lead sync owns that.
 */
final class SyncCampaigns
{
    public function __construct(private LeadAdsProviders $providers) {}

    public function handle(): int
    {
        $saved = 0;

        $connections = IntegrationConnection::query()
            ->where('status', '!=', ConnectionStatus::NeedsReconnect)
            ->get();

        foreach ($connections as $connection) {
            try {
                foreach ($this->providers->for($connection->provider)->fetchCampaigns($connection) as $remote) {
                    $this->store($remote, $connection->id);
                    $saved++;
                }
            } catch (ProviderAuthException|ProviderRequestException) {
                // The lead sync owns connection status. A campaign read can
                // fail without disconnecting an account that still delivers leads.
            }
        }

        Lead::query()
            ->master()
            ->select(['id', 'utm', 'metadata'])
            ->orderBy('id')
            ->chunkById(500, function ($leads) use (&$saved): void {
                foreach ($leads as $lead) {
                    $remote = $this->fromLead($lead);

                    if (! $remote instanceof RemoteCampaign) {
                        continue;
                    }

                    $this->store($remote, null);
                    $saved++;
                }
            });

        return $saved;
    }

    private function fromLead(Lead $lead): ?RemoteCampaign
    {
        $metadata = $lead->metadata ?? [];
        $utm = $lead->utm ?? [];
        $rawPlatform = $metadata['lead_ads_platform'] ?? $utm['utm_source'] ?? null;
        $name = trim((string) ($utm['utm_campaign'] ?? ''));
        $externalId = trim((string) ($metadata['lead_ads_campaign_id'] ?? ''));

        if (! is_string($rawPlatform) || trim($rawPlatform) === '' || ($name === '' && $externalId === '')) {
            return null;
        }

        $id = $externalId !== ''
            ? mb_substr($externalId, 0, 255)
            : 'name:'.mb_substr(Str::slug($name), 0, 240);

        return new RemoteCampaign(
            platform: AdPlatform::normalize($rawPlatform),
            externalId: $id !== '' ? $id : 'name:campaign',
            name: mb_substr($name !== '' ? $name : $externalId, 0, 255),
        );
    }

    private function store(RemoteCampaign $remote, ?int $connectionId): void
    {
        $family = in_array($remote->platform, ['meta', 'facebook', 'instagram'], true)
            ? ['meta', 'facebook', 'instagram']
            : [$remote->platform];

        $campaign = AdCampaign::query()
            ->where('external_id', $remote->externalId)
            ->whereIn('platform', $family)
            ->first();

        if (! $campaign instanceof AdCampaign) {
            $campaign = new AdCampaign([
                'platform' => $remote->platform,
                'external_id' => $remote->externalId,
            ]);
        }

        $campaign->name = $remote->name;
        $campaign->last_seen_at = now();

        if ($remote->status !== null) {
            $campaign->platform = $remote->platform;
            $campaign->status = mb_substr($remote->status, 0, 64);
        }

        if ($remote->objective !== null) {
            $campaign->objective = mb_substr($remote->objective, 0, 64);
        }

        if ($remote->startsAt !== null) {
            $campaign->starts_at = $remote->startsAt;
        }

        if ($remote->endsAt !== null) {
            $campaign->ends_at = $remote->endsAt;
        }

        if ($connectionId !== null) {
            $campaign->integration_connection_id = $connectionId;
        }

        $campaign->save();
    }
}
